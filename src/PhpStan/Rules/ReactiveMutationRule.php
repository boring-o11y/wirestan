<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\PropertyWrites;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A #[Reactive] property belongs to the parent. Livewire hashes it when the component
 * mounts or hydrates and compares when it dehydrates, so any change in between throws
 * CannotMutateReactivePropException. That includes mount(), boot() and render().
 *
 * @implements Rule<ClassMethodsNode>
 */
final class ReactiveMutationRule implements Rule
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

        $reactive = $this->reactiveProperties($class);
        if ($reactive === []) {
            return [];
        }

        $methods = ComponentMethods::fromNode($node);
        $errors = [];

        foreach ($methods->all() as $method) {
            if ($this->writesGoUnnoticed($method)) {
                continue;
            }

            $writes = PropertyWrites::in($method);

            foreach ($writes->named as $write) {
                if (isset($reactive[$write->name])) {
                    $errors[] = $this->error($class, $write->name, $method, $methods->reportingLineWithin($method, $write->line));
                }
            }

            foreach ($writes->mass as $mass) {
                foreach (array_keys($reactive) as $name) {
                    if ($mass->covers($name)) {
                        $errors[] = $this->error($class, $name, $method, $methods->reportingLineWithin($method, $mass->line), $mass->call);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * `__construct` runs before the parent's value is applied, which overwrites the
     * write. `dehydrate` runs after Livewire's comparison.
     */
    private function writesGoUnnoticed(ClassMethod $method): bool
    {
        $name = $method->name->toLowerString();

        return $name === '__construct' || str_starts_with($name, 'dehydrate');
    }

    /**
     * @return array<string, true>
     */
    private function reactiveProperties(ClassReflection $class): array
    {
        $names = [];

        foreach ($class->getNativeReflection()->getProperties() as $property) {
            if (LivewireAttributes::includes($property->getAttributes(), LivewireAttributes::REACTIVE)) {
                $names[$property->getName()] = true;
            }
        }

        return $names;
    }

    private function error(ClassReflection $class, string $property, ClassMethod $method, int $line, ?string $call = null): IdentifierRuleError
    {
        return RuleErrorBuilder::message(\sprintf(
            'Livewire #[Reactive] property %s::$%s is written%s in %s(), which throws CannotMutateReactivePropException. The value belongs to the parent component: change it there, or copy it to another property.',
            $class->getDisplayName(),
            $property,
            $call === null ? '' : ' by ' . $call,
            $method->name->toString(),
        ))
            ->identifier('boringO11yWirestan.reactiveMutation')
            ->line($line)
            ->build();
    }
}
