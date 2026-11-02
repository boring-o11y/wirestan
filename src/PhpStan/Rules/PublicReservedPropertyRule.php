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
 * Properties that configure Livewire must not be public. Livewire has no exclusion
 * list for them, so a public one is dehydrated and client-editable like any other:
 * `$wire.set('rules', [...])` rewrites the validation rules for that component.
 *
 * @implements Rule<Property>
 */
final class PublicReservedPropertyRule implements Rule
{
    private const RESERVED_PROPERTIES = [
        'rules',
        'messages',
        'validationAttributes',
        'validationCustomValues',
        'listeners',
        'queryString',
        'paginationTheme',
    ];

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
        // A lock closes the tampering route this rule is about.
        if (LivewireAttributes::has($node->attrGroups, LivewireAttributes::LOCKED)) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || ! $this->detector->isLivewireComponent($classReflection)) {
            return [];
        }

        $errors = [];
        foreach ($node->props as $prop) {
            $name = $prop->name->toString();
            if (! \in_array($name, self::RESERVED_PROPERTIES, true)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Livewire property %s::$%s configures the framework but is public, so the client can overwrite it via $wire.set. Make it protected.',
                $classReflection->getDisplayName(),
                $name,
            ))
                ->identifier('boringO11yWirestan.publicReservedProperty')
                ->line($prop->getStartLine())
                ->build();
        }

        return $errors;
    }
}
