<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\PropertyTypes;
use BoringO11y\Wirestan\PhpStan\PropertyWrites;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * `$this->reset()` and `$this->pull()` restore each property from a fresh instance:
 *
 * - An unknown name throws ReflectionException, and a non-public one throws an Error.
 * - A typed property with no default has nothing to restore, so Livewire unsets it, and
 *   the next read throws "must not be accessed before initialization". With no
 *   arguments that happens to every such property, like `public Post $post`.
 *
 * @implements Rule<ClassMethodsNode>
 */
final class ResetTargetRule implements Rule
{
    private const BASE_COMPONENT = 'Livewire\\Component';

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
        $constructor = $methods->find('__construct');
        $setByConstructor = $constructor === null ? [] : $this->assignedIn(PropertyWrites::in($constructor));
        $errors = [];

        foreach ($methods->all() as $method) {
            $reassigned = $setByConstructor + $this->assignedIn(PropertyWrites::in($method));

            foreach (ComponentMethods::findIn($method, static fn (Node $node): bool => $node instanceof MethodCall) as $call) {
                if (! $call instanceof MethodCall) {
                    continue;
                }

                $callName = ComponentMethods::thisMember($call);
                if ($callName === null || ! \in_array(strtolower($callName), ['reset', 'pull', 'resetexcept'], true)) {
                    continue;
                }

                $names = PropertyWrites::literalNames($call);
                if ($names === null) {
                    continue;
                }

                $messages = strtolower($callName) === 'resetexcept' || $names === []
                    ? $this->problemsResettingAll($class, $callName, $names, $reassigned)
                    : $this->problemsWithTargets($class, $callName, $names, $reassigned);

                foreach ($messages as $message) {
                    $errors[] = RuleErrorBuilder::message($message)
                        ->identifier('boringO11yWirestan.resetTarget')
                        ->line($methods->reportingLineWithin($method, $call->getStartLine()))
                        ->build();
                }
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, true>  $reassigned
     * @return list<string>
     */
    private function problemsWithTargets(ClassReflection $class, string $call, array $names, array $reassigned): array
    {
        $messages = [];

        foreach ($names as $name) {
            $root = PropertyWrites::rootOf($name);
            $label = \sprintf('$this->%s(\'%s\')', $call, $name);

            if (! $class->hasNativeProperty($root)) {
                $messages[] = \sprintf(
                    'Livewire component %s calls %s, but has no property $%s, which throws at runtime.',
                    $class->getDisplayName(),
                    $label,
                    $root,
                );

                continue;
            }

            // A nested path resets part of the value, so its own rules apply.
            if ($root !== $name) {
                continue;
            }

            if (! $class->getNativeProperty($name)->isPublic()) {
                $messages[] = \sprintf(
                    'Livewire component %s calls %s on a non-public property, which throws at runtime. Assign it directly instead.',
                    $class->getDisplayName(),
                    $label,
                );

                continue;
            }

            if (! isset($reassigned[$name]) && $this->isUnsetByReset($class, $name)) {
                $messages[] = $this->unsetMessage($class, $label, $name);
            }
        }

        return $messages;
    }

    /**
     * @param  list<string>  $except
     * @param  array<string, true>  $reassigned
     * @return list<string>
     */
    private function problemsResettingAll(ClassReflection $class, string $call, array $except, array $reassigned): array
    {
        $kept = array_map(PropertyWrites::rootOf(...), $except);
        $messages = [];

        foreach ($class->getNativeReflection()->getProperties() as $property) {
            $name = $property->getName();

            if (
                ! $property->isPublic()
                || $property->isStatic()
                || $property->getDeclaringClass()->getName() === self::BASE_COMPONENT
                || \in_array($name, $kept, true)
                || isset($reassigned[$name])
                || ! $this->isUnsetByReset($class, $name)
            ) {
                continue;
            }

            $messages[] = $this->unsetMessage($class, \sprintf('$this->%s()', $call), $name);
        }

        return $messages;
    }

    /**
     * Forms are typed with no default too, but Livewire resets their fields instead.
     */
    private function isUnsetByReset(ClassReflection $class, string $name): bool
    {
        $property = $class->getNativeReflection()->getProperty($name);

        return $property->hasType()
            && ! $property->hasDefaultValue()
            && ! $property->isPromoted()
            && ! PropertyTypes::isForm($class, $name);
    }

    private function unsetMessage(ClassReflection $class, string $call, string $name): string
    {
        return \sprintf(
            'Livewire component %s calls %s, which unsets typed property $%s because it has no default value, so the next read throws. Give it a default value, leave it out of the reset, or reassign it in the same method.',
            $class->getDisplayName(),
            $call,
            $name,
        );
    }

    /**
     * Direct assignments, leaving out the reset and pull calls being checked.
     *
     * @return array<string, true>
     */
    private function assignedIn(PropertyWrites $writes): array
    {
        $names = [];

        foreach ($writes->named as $write) {
            if (! $write->viaCall && ! $write->nested) {
                $names[$write->name] = true;
            }
        }

        return $names;
    }
}
