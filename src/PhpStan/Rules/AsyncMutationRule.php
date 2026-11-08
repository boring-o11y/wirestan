<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\PropertyTypes;
use BoringO11y\Wirestan\PhpStan\PropertyWrite;
use BoringO11y\Wirestan\PhpStan\PropertyWrites;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * #[Async] actions run in parallel, each from the same starting snapshot, so concurrent
 * calls overwrite each other's public state. Livewire's docs: "Never use async actions
 * if they modify component state that's reflected in your UI."
 *
 * @implements Rule<ClassMethodsNode>
 */
final class AsyncMutationRule implements Rule
{
    public function __construct(
        private readonly LivewireComponentReflectionDetector $detector,
    ) {}

    public function getNodeType(): string
    {
        return ClassMethodsNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (! $this->detector->isLivewireComponent($class)) {
            return [];
        }

        $methods = ComponentMethods::fromNode($node);
        $errors = [];

        foreach ($methods->all() as $method) {
            if (! LivewireAttributes::has($method->attrGroups, LivewireAttributes::ASYNC)) {
                continue;
            }

            $written = [];

            foreach ($methods->reachableFrom($method) as $reached) {
                $writes = PropertyWrites::in($reached);

                foreach ($writes->named as $write) {
                    if ($this->writesPublicState($write, $class)) {
                        $written[] = '$this->' . $write->name;
                    }
                }

                foreach ($writes->mass as $mass) {
                    $written[] = $mass->call;
                }
            }

            if ($written === []) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Livewire #[Async] action %s::%s() writes %s. Async actions run in parallel from the same starting state, so concurrent calls overwrite each other. Remove #[Async], or don\'t change public properties in this action.',
                $class->getDisplayName(),
                $method->name->toString(),
                implode(', ', array_values(array_unique($written))),
            ))
                ->identifier('boringO11yWirestan.asyncMutation')
                ->line($methods->reportingLine($method))
                ->build();
        }

        return $errors;
    }

    /**
     * Only public properties travel in the snapshot. A change to a model's attributes
     * doesn't either, because the snapshot holds only its class and key.
     */
    private function writesPublicState(PropertyWrite $write, ClassReflection $class): bool
    {
        if (! $class->hasNativeProperty($write->name)) {
            return false;
        }

        $property = $class->getNativeProperty($write->name);
        if (! $property->isPublic() || $property->isStatic()) {
            return false;
        }

        return ! ($write->nested && PropertyTypes::isClientProofModelState($class, $write->name));
    }
}
