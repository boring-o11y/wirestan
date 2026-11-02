<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\RequestContext;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Unset_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

/**
 * A cached #[Computed] value is stored under a key that says nothing about who asked
 * or what the component's state was. Per BaseComputed:
 *
 * - `cache: true`, or `persist: true` with a `key`, is shared by every visitor, so a
 *   value derived from the user, the request or component state leaks across users.
 * - `persist: true` alone is shared by one component instance, so a value derived from
 *   a property that can change goes stale unless something busts it with unset().
 *
 * @implements Rule<ClassMethodsNode>
 */
final class ComputedCacheScopeRule implements Rule
{
    /**
     * Livewire keeps the identity of these tamper-proof, so they don't change under
     * a persisted value the way other public properties can.
     */
    private const URL_ROUTABLE_FQCN = 'Illuminate\\Contracts\\Routing\\UrlRoutable';

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
            $attribute = LivewireAttributes::all($method->attrGroups, LivewireAttributes::COMPUTED)[0] ?? null;
            if ($attribute === null) {
                continue;
            }

            $message = $this->problemWith($attribute, $method, $methods, $class);
            if ($message === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message($message)
                ->identifier('boringO11yWirestan.computedCacheScope')
                ->line($methods->reportingLine($method))
                ->build();
        }

        return $errors;
    }

    private function problemWith(Attribute $attribute, ClassMethod $method, ComponentMethods $methods, ClassReflection $class): ?string
    {
        $persist = $this->isTrue(LivewireAttributes::argument($attribute, 'persist', 0));
        $cache = $this->isTrue(LivewireAttributes::argument($attribute, 'cache', 2));

        if (! $persist && ! $cache) {
            return null;
        }

        // Same precedence as BaseComputed::handleMagicGet(): persist wins over cache.
        $sharedAcrossUsers = $persist ? $this->hasKey($attribute) : true;
        $reachable = $methods->reachableFrom($method);

        if ($sharedAcrossUsers) {
            $reads = [...$this->contextReads($reachable), ...$this->stateReads($reachable, $class)];

            return $reads === [] ? null : \sprintf(
                'Livewire computed property %s::%s() is cached for all users but depends on %s, so every visitor receives the first caller\'s value. Use #[Computed(persist: true)] without a key, or remove the caching.',
                $class->getDisplayName(),
                $method->name->toString(),
                implode(', ', $reads),
            );
        }

        if ($this->isBusted($method, $methods)) {
            return null;
        }

        $reads = $this->stateReads($reachable, $class, onlyMutable: true);

        return $reads === [] ? null : \sprintf(
            'Livewire computed property %s::%s() is cached per component (persist: true) but depends on %s, which can change without the cached value updating. Bust it with unset($this->%s), mark the property #[Locked], or remove persist.',
            $class->getDisplayName(),
            $method->name->toString(),
            implode(', ', $reads),
            $method->name->toString(),
        );
    }

    private function isTrue(?Expr $expression): bool
    {
        return $expression instanceof ConstFetch && $expression->name->toLowerString() === 'true';
    }

    private function hasKey(Attribute $attribute): bool
    {
        $key = LivewireAttributes::argument($attribute, 'key', 3);

        return $key !== null && ! ($key instanceof ConstFetch && $key->name->toLowerString() === 'null');
    }

    private function isBusted(ClassMethod $computed, ComponentMethods $methods): bool
    {
        $name = $computed->name->toString();

        foreach ($methods->all() as $method) {
            foreach (ComponentMethods::findIn($method, static fn (Node $node): bool => $node instanceof Unset_) as $unset) {
                if (! $unset instanceof Unset_) {
                    continue;
                }
                foreach ($unset->vars as $var) {
                    if (ComponentMethods::thisMember($var) === $name) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  list<ClassMethod>  $methods
     * @return list<string>
     */
    private function contextReads(array $methods): array
    {
        $reads = [];

        foreach ($methods as $method) {
            foreach (ComponentMethods::findIn($method, static fn (Node $node): bool => RequestContext::read($node) !== null) as $node) {
                $reads[] = (string) RequestContext::read($node);
            }
        }

        return array_values(array_unique($reads));
    }

    /**
     * Reads of public properties, the state that varies between component instances.
     * Non-public properties aren't persisted between requests, so they generally hold
     * fixed values or services and are left out to avoid false positives.
     *
     * @param  list<ClassMethod>  $methods
     * @return list<string>
     */
    private function stateReads(array $methods, ClassReflection $class, bool $onlyMutable = false): array
    {
        $reads = [];

        foreach ($methods as $method) {
            foreach (ComponentMethods::findIn($method, static fn (Node $node): bool => $node instanceof PropertyFetch) as $node) {
                $name = ComponentMethods::thisMember($node);
                if ($name === null || ! $class->hasNativeProperty($name)) {
                    continue;
                }

                $property = $class->getNativeProperty($name);
                if (! $property->isPublic() || $property->isStatic()) {
                    continue;
                }
                if ($onlyMutable && $this->cannotChangeFromClient($class, $name)) {
                    continue;
                }

                $reads[] = '$this->' . $name;
            }
        }

        return array_values(array_unique($reads));
    }

    private function cannotChangeFromClient(ClassReflection $class, string $name): bool
    {
        if (LivewireAttributes::includes($class->getNativeReflection()->getProperty($name)->getAttributes(), LivewireAttributes::LOCKED)) {
            return true;
        }

        $type = TypeCombinator::removeNull($class->getNativeProperty($name)->getReadableType());

        return (new ObjectType(self::URL_ROUTABLE_FQCN))->isSuperTypeOf($type)->yes();
    }
}
