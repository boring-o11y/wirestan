<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * #[Authorize('update', 'post')] looks up its target at runtime, in the same order
 * as BaseAuthorize::resolveArgument(): a class, then a method parameter, then a
 * property path on the component. A target that matches nothing reaches the gate as
 * null, and an untyped parameter reaches it as the raw client value, so the check
 * silently runs against the wrong thing.
 *
 * @implements Rule<InClassMethodNode>
 */
final class AuthorizeTargetRule implements Rule
{
    public function __construct(
        private readonly LivewireComponentReflectionDetector $detector,
        private readonly ReflectionProvider $reflectionProvider,
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
        $errors = [];

        foreach (LivewireAttributes::all($method->attrGroups, LivewireAttributes::AUTHORIZE) as $attribute) {
            $argument = LivewireAttributes::argument($attribute, 'argument', 1);
            if ($argument === null) {
                continue;
            }

            foreach ($this->targets($argument, $scope) as $target) {
                $message = $this->problemWith($target, $method, $classReflection);
                if ($message === null) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message($message)
                    ->identifier('boringO11yWirestan.authorizeTarget')
                    ->line($attribute->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function targets(Expr $argument, Scope $scope): array
    {
        $expressions = [$argument];

        if ($argument instanceof Array_) {
            $expressions = [];
            foreach ($argument->items as $item) {
                if ($item !== null) {
                    $expressions[] = $item->value;
                }
            }
        }

        $targets = [];
        foreach ($expressions as $expression) {
            $strings = $scope->getType($expression)->getConstantStrings();
            if (\count($strings) === 1) {
                $targets[] = $strings[0]->getValue();
            }
        }

        return $targets;
    }

    private function problemWith(string $target, ClassMethod $method, ClassReflection $class): ?string
    {
        if ($this->reflectionProvider->hasClass($target)) {
            return null;
        }

        $parameter = $this->parameterNamed($target, $method);
        if ($parameter !== null) {
            return $parameter->type !== null ? null : \sprintf(
                'Livewire action %s::%s() has #[Authorize] targeting $%s, which has no type hint. Livewire needs the type to resolve the model, so the gate receives the raw client value.',
                $class->getDisplayName(),
                $method->name->toString(),
                $target,
            );
        }

        if ($this->isComponentMember(explode('.', $target)[0], $class)) {
            return null;
        }

        return \sprintf(
            'Livewire action %s::%s() has #[Authorize] targeting "%s", which is not a class, a parameter of the method, or a public or computed property of the component. The gate receives null instead of the object it should check.',
            $class->getDisplayName(),
            $method->name->toString(),
            $target,
        );
    }

    private function parameterNamed(string $name, ClassMethod $method): ?Param
    {
        foreach ($method->params as $param) {
            if ($param->var instanceof Variable && $param->var->name === $name) {
                return $param;
            }
        }

        return null;
    }

    /**
     * The target is read from outside the component, so only what's visible there
     * resolves: public properties, and computed properties through __get().
     */
    private function isComponentMember(string $name, ClassReflection $class): bool
    {
        if ($class->hasNativeProperty($name)) {
            $property = $class->getNativeProperty($name);

            return $property->isPublic() && ! $property->isStatic();
        }

        $reflection = $class->getNativeReflection();

        if ($reflection->hasMethod($name) && LivewireAttributes::includes($reflection->getMethod($name)->getAttributes(), LivewireAttributes::COMPUTED)) {
            return true;
        }

        $legacyAccessor = 'get' . ucfirst($name) . 'Property';

        return $reflection->hasMethod($legacyAccessor) && $reflection->getMethod($legacyAccessor)->isPublic();
    }
}
