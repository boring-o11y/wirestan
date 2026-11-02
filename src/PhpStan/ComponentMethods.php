<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;

/**
 * One component's methods: its own and those pulled in from traits. PHPStan's AST
 * doesn't include method bodies from parent classes, so nothing here can see them.
 */
final class ComponentMethods
{
    /**
     * Methods Livewire refuses to run from a client call, mirroring
     * HandleComponents::callMethods() and SupportLifecycleHooks::call().
     */
    private const LIFECYCLE_METHODS = [
        'mount',
        'boot',
        'booted',
        'exception',
        'render',
        'rendering',
        'rendered',
        'scriptSrc',
    ];

    private const LIFECYCLE_PREFIXES = [
        'hydrate',
        'dehydrate',
        'updating',
        'updated',
    ];

    /**
     * Public by convention. Livewire reads these to configure the component, so
     * they aren't actions a user would trigger.
     */
    private const CONFIGURATION_METHODS = [
        'rules',
        'messages',
        'validationAttributes',
        'validationCustomValues',
        'placeholder',
        'with',
        'getListeners',
        'queryString',
        'paginationView',
        'paginationSimpleView',
    ];

    /**
     * @param  array<string, ClassMethod>  $methods  keyed by lowercase name
     * @param  array<string, true>  $traitMethods  lowercase names of methods declared in traits
     */
    private function __construct(
        private readonly array $methods,
        private readonly array $traitMethods,
        private readonly int $classLine,
    ) {}

    public static function fromNode(ClassMethodsNode $node): self
    {
        $methods = [];
        $traitMethods = [];

        foreach ($node->getMethods() as $method) {
            $name = $method->getNode()->name->toLowerString();
            $methods[$name] = $method->getNode();

            if ($method->isDeclaredInTrait()) {
                $traitMethods[$name] = true;
            } else {
                unset($traitMethods[$name]);
            }
        }

        return new self($methods, $traitMethods, $node->getClass()->getStartLine());
    }

    /**
     * @return list<ClassMethod>
     */
    public function all(): array
    {
        return array_values($this->methods);
    }

    public function find(string $name): ?ClassMethod
    {
        return $this->methods[strtolower($name)] ?? null;
    }

    /**
     * Where to report an error about a method. The error belongs to the file of
     * the class being analysed, so a line inside a trait would point at the wrong
     * place; those are reported on the class that uses the trait.
     */
    public function reportingLine(ClassMethod $method): int
    {
        return isset($this->traitMethods[$method->name->toLowerString()])
            ? $this->classLine
            : $method->name->getStartLine();
    }

    /**
     * The method itself plus everything it reaches through `$this->method()` or a
     * `$this->computedProperty` read.
     *
     * @return list<ClassMethod>
     */
    public function reachableFrom(ClassMethod $entry): array
    {
        /** @var array<string, ClassMethod> $reachable */
        $reachable = [];
        /** @var list<ClassMethod> $queue */
        $queue = [$entry];

        while ($queue !== []) {
            $method = array_shift($queue);
            $key = $method->name->toLowerString();
            if (isset($reachable[$key])) {
                continue;
            }
            $reachable[$key] = $method;

            foreach (self::findIn($method, static fn (Node $node): bool => self::thisMember($node) !== null) as $node) {
                $target = $this->find((string) self::thisMember($node));
                if ($target === null) {
                    continue;
                }
                if ($node instanceof PropertyFetch && ! LivewireAttributes::has($target->attrGroups, LivewireAttributes::COMPUTED)) {
                    continue;
                }

                $queue[] = $target;
            }
        }

        return array_values($reachable);
    }

    /**
     * Whether a browser can trigger this method as a user-facing action.
     */
    public static function isAction(ClassMethod $method, ClassReflection $class): bool
    {
        if (! self::isClientCallable($method)) {
            return false;
        }

        $name = $method->name->toString();

        if (\in_array($name, self::LIFECYCLE_METHODS, true) || \in_array($name, self::CONFIGURATION_METHODS, true)) {
            return false;
        }

        foreach (self::LIFECYCLE_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return false;
            }
        }

        foreach ($class->getTraits(true) as $trait) {
            $traitName = $trait->getNativeReflection()->getShortName();
            if (\in_array($name, ['mount' . $traitName, 'boot' . $traitName, 'booted' . $traitName], true)) {
                return false;
            }
        }

        return ! LivewireAttributes::has($method->attrGroups, LivewireAttributes::COMPUTED);
    }

    public static function isClientCallable(ClassMethod $method): bool
    {
        return $method->isPublic()
            && ! $method->isStatic()
            && ! str_starts_with($method->name->toString(), '__');
    }

    /**
     * @param  callable(Node): bool  $filter
     * @return array<Node>
     */
    public static function findIn(ClassMethod $method, callable $filter): array
    {
        return (new NodeFinder)->find($method->stmts ?? [], $filter);
    }

    /**
     * The member name when the node is `$this->name` or `$this->name()`.
     */
    public static function thisMember(Node $node): ?string
    {
        if (! $node instanceof MethodCall && ! $node instanceof PropertyFetch) {
            return null;
        }
        if (! $node->var instanceof Variable || $node->var->name !== 'this') {
            return null;
        }

        return $node->name instanceof Identifier ? $node->name->toString() : null;
    }
}
