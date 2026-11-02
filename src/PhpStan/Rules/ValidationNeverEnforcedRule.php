<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validation rules don't stop an action. #[Validate] records errors when a property
 * updates, but the action still runs afterwards, and rules() does nothing until
 * validate() is called. A component with rules and actions but no validate() call
 * accepts whatever the client sends.
 *
 * @implements Rule<ClassMethodsNode>
 */
final class ValidationNeverEnforcedRule implements Rule
{
    private const BASE_COMPONENTS = ['Livewire\\Component', 'Livewire\\Volt\\Component'];

    private const VALIDATION_CALLS = ['validate', 'validateonly'];

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
        if (! $this->detector->isLivewireComponent($class) || ! $this->canSeeWholeComponent($class)) {
            return [];
        }

        $methods = ComponentMethods::fromNode($node);

        if (! $this->declaresRules($class, $methods) || ! $this->hasAction($class, $methods) || $this->callsValidation($methods)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Livewire component %s declares validation rules but never calls validate() or validateOnly(). Rules alone do not stop an action from running: call $this->validate() before using the input.',
                $class->getDisplayName(),
            ))
                ->identifier('boringO11yWirestan.validationNeverEnforced')
                ->line($node->getClass()->getStartLine())
                ->build(),
        ];
    }

    /**
     * A subclass or a custom base class may hold the validate() call, and method
     * bodies outside this class aren't visible, so absence can't be proven there.
     */
    private function canSeeWholeComponent(ClassReflection $class): bool
    {
        $parent = $class->getParentClass();

        return ! $class->isAbstract()
            && $parent !== null
            && \in_array($parent->getName(), self::BASE_COMPONENTS, true);
    }

    private function declaresRules(ClassReflection $class, ComponentMethods $methods): bool
    {
        if ($methods->find('rules') !== null || $class->hasNativeProperty('rules')) {
            return true;
        }

        foreach ($class->getNativeReflection()->getProperties() as $property) {
            if (LivewireAttributes::includes($property->getAttributes(), LivewireAttributes::VALIDATE, LivewireAttributes::RULE)) {
                return true;
            }
        }

        return false;
    }

    private function hasAction(ClassReflection $class, ComponentMethods $methods): bool
    {
        foreach ($methods->all() as $method) {
            if (ComponentMethods::isAction($method, $class)) {
                return true;
            }
        }

        return false;
    }

    private function callsValidation(ComponentMethods $methods): bool
    {
        foreach ($methods->all() as $method) {
            if (ComponentMethods::findIn($method, $this->isValidationCall(...)) !== []) {
                return true;
            }
        }

        return false;
    }

    private function isValidationCall(Node $node): bool
    {
        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier
            && \in_array($node->name->toLowerString(), self::VALIDATION_CALLS, true);
    }
}
