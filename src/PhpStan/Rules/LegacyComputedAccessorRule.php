<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A legacy `getFooProperty()` accessor is an ordinary public method, so the browser
 * can call it and read what it returns. #[Computed] methods refuse client calls.
 * Making the accessor protected isn't a fix: Livewire only recognises public ones.
 *
 * @implements Rule<InClassMethodNode>
 */
final class LegacyComputedAccessorRule implements Rule
{
    public function __construct(
        private readonly LivewireComponentReflectionDetector $detector,
    ) {}

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();
        if (! $this->detector->isLivewireComponent($classReflection)) {
            return [];
        }

        $method = $node->getOriginalNode();
        $name = $method->name->toString();

        if (! $method->isPublic() || $method->isStatic() || preg_match('/^get.+Property$/', $name) !== 1) {
            return [];
        }
        if (LivewireAttributes::has($method->attrGroups, LivewireAttributes::COMPUTED)) {
            return [];
        }

        // A required parameter means this can't be used as an accessor, whatever its name.
        foreach ($method->params as $param) {
            if ($param->default === null && ! $param->variadic) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Livewire accessor %s::%s() can be called directly from the client, which then receives its return value. Convert it to a #[Computed] method, which Livewire refuses to call from the client.',
                $classReflection->getDisplayName(),
                $name,
            ))
                ->identifier('boringO11yWirestan.legacyComputedAccessor')
                ->line($method->name->getStartLine())
                ->build(),
        ];
    }
}
