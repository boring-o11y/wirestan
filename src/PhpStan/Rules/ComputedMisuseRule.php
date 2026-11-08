<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Ways to write a #[Computed] method that Livewire can't honour: it's called with no
 * arguments and no dependency injection, any same-named instance property answers
 * `$this->name` first, Forms ignore the attribute, and calling the method skips the
 * memoized value.
 *
 * @implements Rule<ClassMethodsNode>
 */
final class ComputedMisuseRule implements Rule
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
        $isForm = $this->detector->isLivewireForm($class);

        if (! $isForm && ! $this->detector->isLivewireComponent($class)) {
            return [];
        }

        $methods = ComponentMethods::fromNode($node);
        $computed = array_values(array_filter(
            $methods->all(),
            static fn (ClassMethod $method): bool => LivewireAttributes::has($method->attrGroups, LivewireAttributes::COMPUTED),
        ));

        if ($computed === []) {
            return [];
        }

        $errors = [];

        foreach ($computed as $method) {
            $line = $methods->reportingLine($method);

            if ($isForm) {
                $errors[] = $this->error(\sprintf(
                    'Computed properties aren\'t supported on Livewire Form objects: %s::%s() has #[Computed], but reading $form->%s throws "Undefined property". Move it to the component.',
                    $class->getDisplayName(),
                    $method->name->toString(),
                    $method->name->toString(),
                ), $line);

                continue;
            }

            foreach ($this->problemsWith($method, $class) as $message) {
                $errors[] = $this->error($message, $line);
            }
        }

        return $isForm ? $errors : [...$errors, ...$this->directCalls($computed, $methods, $class)];
    }

    /**
     * @return list<string>
     */
    private function problemsWith(ClassMethod $method, ClassReflection $class): array
    {
        $name = $method->name->toString();
        $problems = [];

        foreach ($method->params as $param) {
            if ($param->default === null && ! $param->variadic && $param->var instanceof Node\Expr\Variable && \is_string($param->var->name)) {
                $problems[] = \sprintf(
                    'Livewire computed property %s::%s() has required parameter $%s, which throws ArgumentCountError: Livewire calls computed methods with no arguments and doesn\'t inject dependencies. Give it a default value or remove it.',
                    $class->getDisplayName(),
                    $name,
                    $param->var->name,
                );
            }
        }

        if ($this->hasShadowingProperty($class, $name)) {
            $problems[] = \sprintf(
                'Livewire computed property %s::%s() never runs when read as $this->%s, because a property with the same name answers first. Rename one of them.',
                $class->getDisplayName(),
                $name,
                $name,
            );
        }

        return $problems;
    }

    /**
     * A private property on a parent class isn't visible here, so PHP falls through to
     * Livewire's magic getter. A static one isn't read by `$this->name` either.
     */
    private function hasShadowingProperty(ClassReflection $class, string $name): bool
    {
        if (! $class->hasNativeProperty($name)) {
            return false;
        }

        $property = $class->getNativeProperty($name);

        if ($property->isStatic()) {
            return false;
        }

        return ! $property->isPrivate() || $property->getDeclaringClass()->getName() === $class->getName();
    }

    /**
     * `$this->rows(5)` is the only way to pass an optional argument, so only calls with
     * none are reported.
     *
     * @param  list<ClassMethod>  $computed
     * @return list<IdentifierRuleError>
     */
    private function directCalls(array $computed, ComponentMethods $methods, ClassReflection $class): array
    {
        $names = [];
        foreach ($computed as $method) {
            $names[$method->name->toLowerString()] = $method->name->toString();
        }

        $errors = [];

        foreach ($methods->all() as $caller) {
            foreach (ComponentMethods::findIn($caller, static fn (Node $node): bool => $node instanceof MethodCall) as $call) {
                if (! $call instanceof MethodCall || $call->isFirstClassCallable() || $call->getArgs() !== []) {
                    continue;
                }

                $member = ComponentMethods::thisMember($call);
                $name = $member === null ? null : ($names[strtolower($member)] ?? null);

                if ($name === null) {
                    continue;
                }

                $errors[] = $this->error(\sprintf(
                    'Calling %s::%s() directly runs it again and skips #[Computed]. Read $this->%s instead, which is memoized for the request.',
                    $class->getDisplayName(),
                    $name,
                    $name,
                ), $methods->reportingLineWithin($caller, $call->getStartLine()));
            }
        }

        return $errors;
    }

    private function error(string $message, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('boringO11yWirestan.computedMisuse')
            ->line($line)
            ->build();
    }
}
