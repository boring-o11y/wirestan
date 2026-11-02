<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\AuthorizationGuardDetector;
use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

/**
 * Livewire resolves a route-bindable action parameter from whatever id the client
 * sends, so `delete(Post $post)` lets a browser name any record. The same applies to
 * #[On] handlers, which the client can reach by dispatching the event itself.
 *
 * @implements Rule<ClassMethodsNode>
 */
final class ModelParamWithoutAuthorizationRule implements Rule
{
    /**
     * What ImplicitlyBoundMethod binds on. Eloquent models implement it.
     */
    private const URL_ROUTABLE_FQCN = 'Illuminate\\Contracts\\Routing\\UrlRoutable';

    public function __construct(
        private readonly LivewireComponentReflectionDetector $detector,
        private readonly AuthorizationGuardDetector $guards,
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
            if (! $this->isReachableFromClient($method, $class)) {
                continue;
            }
            if (LivewireAttributes::has($method->attrGroups, LivewireAttributes::AUTHORIZE)) {
                continue;
            }

            $bound = $this->boundParameters($method, $class);
            if ($bound === [] || $this->guards->covers($method, $methods)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Livewire action %s::%s() resolves %s from an id the client supplies, so a browser can name any record, but it has no authorization check. Add #[Authorize], call $this->authorize(), or list your own guard under wirestan.authorizationMethods.',
                $class->getDisplayName(),
                $method->name->toString(),
                implode(', ', array_map(static fn (string $name): string => '$' . $name, $bound)),
            ))
                ->identifier('boringO11yWirestan.modelParamWithoutAuthorization')
                ->line($methods->reportingLine($method))
                ->build();
        }

        return $errors;
    }

    private function isReachableFromClient(ClassMethod $method, ClassReflection $class): bool
    {
        if (ComponentMethods::isAction($method, $class)) {
            return true;
        }

        return ComponentMethods::isClientCallable($method)
            && LivewireAttributes::has($method->attrGroups, LivewireAttributes::ON);
    }

    /**
     * @return list<string>
     */
    private function boundParameters(ClassMethod $method, ClassReflection $class): array
    {
        $name = $method->name->toString();
        if (! $class->hasNativeMethod($name)) {
            return [];
        }

        $urlRoutable = new ObjectType(self::URL_ROUTABLE_FQCN);
        $bound = [];

        foreach ($class->getNativeMethod($name)->getOnlyVariant()->getParameters() as $parameter) {
            $type = TypeCombinator::removeNull($parameter->getNativeType());

            if ($urlRoutable->isSuperTypeOf($type)->yes()) {
                $bound[] = $parameter->getName();
            }
        }

        return $bound;
    }
}
