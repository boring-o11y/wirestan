<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * #[Locked] only blocks client updates. #[Url] writes the query-string value during
 * mount, which the lock doesn't intercept, so the visitor still picks the value of a
 * property the code treats as server-controlled.
 *
 * @implements Rule<Property>
 */
final class LockedUrlConflictRule implements Rule
{
    public function __construct(
        private readonly LivewireComponentReflectionDetector $detector,
    ) {}

    public function getNodeType(): string
    {
        return Property::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->isPublic() || $node->isStatic()) {
            return [];
        }
        if (! LivewireAttributes::has($node->attrGroups, LivewireAttributes::LOCKED)
            || ! LivewireAttributes::has($node->attrGroups, LivewireAttributes::URL)) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }
        if (! $this->detector->isLivewireComponent($classReflection) && ! $this->detector->isLivewireForm($classReflection)) {
            return [];
        }

        $errors = [];
        foreach ($node->props as $prop) {
            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Livewire public property %s::$%s is both #[Locked] and #[Url]. The lock only blocks updates: #[Url] still sets the value from the query string on page load, so the visitor chooses it. Remove #[Url], or remove #[Locked] and treat the value as user input.',
                $classReflection->getDisplayName(),
                $prop->name->toString(),
            ))
                ->identifier('boringO11yWirestan.lockedUrlConflict')
                ->line($prop->getStartLine())
                ->build();
        }

        return $errors;
    }
}
