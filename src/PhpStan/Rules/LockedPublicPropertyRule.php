<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\ComponentViews;
use BoringO11y\Wirestan\PhpStan\LivewireAttributes;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\PropertyTypes;
use BoringO11y\Wirestan\PhpStan\PropertyWrites;
use BoringO11y\Wirestan\PhpStan\ViewBindings;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Public Livewire properties that are never reassigned outside lifecycle
 * seed methods (see SEED_METHODS: mount / __construct / boot / booted /
 * hydrate / dehydrate) must carry #[Livewire\Attributes\Locked]. Any unlocked
 * public property is editable by the client via $wire.set, which becomes an
 * IDOR / data-tampering vector for fields that the server is supposed to
 * control.
 *
 * A property the code treats as client input is left alone, because locking it throws
 * CannotUpdateLockedPropertyException: one bound in a view, with an input attribute or
 * an update hook, or named in validation rules. So is one the client can't write.
 *
 * @implements Rule<ClassMethodsNode>
 */
final class LockedPublicPropertyRule implements Rule
{
    /**
     * Lifecycle methods whose property writes count as "seeding" (initial,
     * server-controlled state), not as a user-driven mutation. A property
     * written only inside these is still required to be #[Locked].
     */
    private const SEED_METHODS = [
        'mount',
        '__construct',
        'boot',
        'booted',
        'hydrate',
        'dehydrate',
    ];

    /**
     * Mark a property as input the client sets, or, for #[Reactive], as one it can't.
     * #[Url] also keeps LockedUrlConflictRule satisfiable: it reports the lock.
     */
    private const INPUT_ATTRIBUTES = [
        LivewireAttributes::MODELABLE,
        LivewireAttributes::REACTIVE,
        LivewireAttributes::RULE,
        LivewireAttributes::SESSION,
        LivewireAttributes::URL,
        LivewireAttributes::VALIDATE,
    ];

    /**
     * @param  list<string>  $reservedProperties
     */
    public function __construct(
        private readonly LivewireComponentReflectionDetector $detector,
        private readonly ComponentViews $views,
        private readonly array $reservedProperties = [],
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
        $classReflection = $node->getClassReflection();
        if (! $this->detector->isLivewireComponent($classReflection)) {
            return [];
        }

        $originalNode = $node->getClass();
        if (! $originalNode instanceof Class_) {
            return [];
        }

        $publicProperties = $this->collectPublicProperties($originalNode);
        if ($publicProperties === []) {
            return [];
        }

        $methods = ComponentMethods::fromNode($node);
        $mutated = $this->mutatedOutsideSeedMethods($methods);

        // An opaque mass-assignment (`$this->fill()`, no-arg `$this->reset()`,
        // `resetExcept()`, or a dynamic `$this->{$x} = …`) can touch any public
        // property, so immutability can't be proven — skip the whole component.
        if ($mutated === null) {
            return [];
        }

        $validated = $this->validatedProperties($originalNode, $methods);
        $bindings = null;

        $errors = [];
        foreach ($publicProperties as $name => $propertyContext) {
            if (isset($mutated[$name]) || isset($validated[$name])) {
                continue;
            }
            if (\in_array($name, $this->reservedProperties, true)) {
                continue;
            }
            if (LivewireAttributes::has($propertyContext['property']->attrGroups, LivewireAttributes::LOCKED, ...self::INPUT_ATTRIBUTES)) {
                continue;
            }
            // A Form's fields are updated through `wire:model="form.field"`, so its
            // property reads as immutable. Locking the root breaks every one of those
            // updates at runtime with CannotUpdateLockedPropertyException.
            if (PropertyTypes::isForm($classReflection, $name)) {
                continue;
            }
            if (PropertyTypes::isClientProofModelState($classReflection, $name)) {
                continue;
            }
            if ($this->hasUpdateHook($methods, $name)) {
                continue;
            }

            $bindings ??= $this->views->bindingsOf($node, $methods, $scope);
            if ($bindings->binds($name)) {
                continue;
            }

            $error = RuleErrorBuilder::message(\sprintf(
                'Livewire public property %s::$%s is never reassigned outside lifecycle methods. It must be marked #[Livewire\Attributes\Locked] — otherwise the client can mutate it via $wire.set.',
                $classReflection->getDisplayName(),
                $name,
            ))
                ->identifier('boringO11yWirestan.lockedPublicProperty')
                ->line($propertyContext['line']);

            if (! $bindings->complete) {
                $error->tip('wirestan could not read every view this component renders, so a binding may be missing here. If a wire:model does bind this property, ignore this error rather than locking it: a locked input throws CannotUpdateLockedPropertyException.');
            }

            $errors[] = $error->build();
        }

        return $errors;
    }

    /**
     * @return array<string, array{property: Property, line: int}>
     */
    private function collectPublicProperties(Class_ $class): array
    {
        $result = [];

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }
            if (! $stmt->isPublic() || $stmt->isStatic() || $stmt->isReadonly()) {
                continue;
            }

            foreach ($stmt->props as $prop) {
                $result[$prop->name->toString()] = [
                    'property' => $stmt,
                    'line' => $prop->getStartLine(),
                ];
            }
        }

        return $result;
    }

    /**
     * The properties written by any non-seed method, or null when one of them does
     * something that could write any property.
     *
     * @return array<string, true>|null
     */
    private function mutatedOutsideSeedMethods(ComponentMethods $methods): ?array
    {
        $mutated = [];

        foreach ($methods->all() as $method) {
            if (\in_array($method->name->toLowerString(), self::SEED_METHODS, true)) {
                continue;
            }

            $writes = PropertyWrites::in($method);
            if ($writes->dynamic || $writes->mass !== []) {
                return null;
            }

            foreach ($writes->named as $write) {
                $mutated[$write->name] = true;
            }
        }

        return $mutated;
    }

    /**
     * Livewire calls `updatedItems` or `updatedItemsName` when the client updates
     * `items` or `items.name`, so such a hook means the property is expected to change.
     */
    private function hasUpdateHook(ComponentMethods $methods, string $property): bool
    {
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $property)));

        foreach ($methods->all() as $method) {
            $hook = preg_replace('/^(?:updating|updated)/', '', $method->name->toString(), 1, $count);

            if ($count === 1 && preg_match('/^' . preg_quote($studly, '/') . '(?:[A-Z0-9_]|$)/', (string) $hook) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Properties named in `$rules`, `rules()`, or a literal `validate()` or
     * `validateOnly()` call, which all treat the property as input.
     *
     * @return array<string, true>
     */
    private function validatedProperties(Class_ $class, ComponentMethods $methods): array
    {
        $paths = [];

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }
            foreach ($stmt->props as $prop) {
                if ($prop->name->toString() === 'rules' && $prop->default instanceof Array_) {
                    $paths = [...$paths, ...$this->literalKeys($prop->default)];
                }
            }
        }

        $rules = $methods->find('rules');
        if ($rules !== null) {
            foreach (ComponentMethods::findIn($rules, static fn (Node $node): bool => $node instanceof Return_) as $return) {
                if ($return instanceof Return_ && $return->expr instanceof Array_) {
                    $paths = [...$paths, ...$this->literalKeys($return->expr)];
                }
            }
        }

        foreach ($methods->all() as $method) {
            foreach (ComponentMethods::findIn($method, static fn (Node $node): bool => $node instanceof MethodCall) as $call) {
                if ($call instanceof MethodCall) {
                    $paths = [...$paths, ...$this->pathsValidatedBy($call)];
                }
            }
        }

        $names = [];
        foreach ($paths as $path) {
            $names[PropertyWrites::rootOf($path)] = true;
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function pathsValidatedBy(MethodCall $call): array
    {
        $method = ComponentMethods::thisMember($call);
        if ($method === null || $call->isFirstClassCallable()) {
            return [];
        }

        $first = $call->getArgs()[0]->value ?? null;

        return match (strtolower($method)) {
            'validate' => $first instanceof Array_ ? $this->literalKeys($first) : [],
            'validateonly' => $first instanceof String_ ? [$first->value] : [],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function literalKeys(Array_ $array): array
    {
        $keys = [];

        foreach ($array->items as $item) {
            if ($item->key instanceof String_) {
                $keys[] = $item->key->value;
            }
        }

        return $keys;
    }
}
