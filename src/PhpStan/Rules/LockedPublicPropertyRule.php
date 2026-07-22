<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Public Livewire properties that are never reassigned outside lifecycle
 * seed methods (see SEED_METHODS: mount / __construct / boot / booted /
 * hydrate / dehydrate) must carry #[Livewire\Attributes\Locked]. Any unlocked
 * public property is editable by the client via $wire.set, which becomes an
 * IDOR / data-tampering vector for fields that the server is supposed to
 * control.
 *
 * @implements Rule<InClassNode>
 */
final class LockedPublicPropertyRule implements Rule
{
    private const LOCKED_ATTRIBUTE_FQCN = 'Livewire\\Attributes\\Locked';

    private const LIVEWIRE_FORM_FQCN = 'Livewire\\Form';

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
     * `$this->` calls that can mass-assign arbitrary public properties. When a
     * non-seed method uses one we cannot statically prove any public property
     * is immutable, so we treat them all as mutated (no Locked requirement)
     * rather than emit false positives.
     */
    private const OPAQUE_MUTATORS = [
        'fill',
        'fillData',
        'mergeData',
        'reset',
        'resetExcept',
    ];

    /**
     * @param  list<string>  $reservedProperties
     */
    public function __construct(
        private readonly LivewireComponentReflectionDetector $detector,
        private readonly array $reservedProperties = [],
    ) {}

    public function getNodeType(): string
    {
        return InClassNode::class;
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

        $originalNode = $node->getOriginalNode();
        if (! $originalNode instanceof Class_) {
            return [];
        }

        $publicProperties = $this->collectPublicProperties($originalNode);
        if ($publicProperties === []) {
            return [];
        }

        ['opaque' => $opaque, 'mutated' => $mutated] = $this->analyseMutationsOutsideSeedMethods($originalNode);

        // An opaque mass-assignment (`$this->fill()`, no-arg `$this->reset()`,
        // `resetExcept()`, or a dynamic `$this->{$x} = …`) can touch any public
        // property, so immutability can't be proven — skip the whole component.
        if ($opaque) {
            return [];
        }

        $errors = [];
        foreach ($publicProperties as $name => $propertyContext) {
            if (\in_array($name, $mutated, true)) {
                continue;
            }
            if (\in_array($name, $this->reservedProperties, true)) {
                continue;
            }
            // Livewire\Form properties are mutated through nested fields
            // (wire:model="form.field") and never reassigned wholesale, so they
            // read as immutable here. Locking the root breaks every nested
            // update at runtime (CannotUpdateLockedPropertyException) — Livewire
            // de/hydrates Forms natively, so they're never a $wire.set vector.
            if ($this->isFormProperty($classReflection, $name)) {
                continue;
            }
            if ($this->hasLockedAttribute($propertyContext['property'])) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Livewire public property %s::$%s is never reassigned outside lifecycle methods. It must be marked #[Livewire\Attributes\Locked] — otherwise the client can mutate it via $wire.set.',
                $classReflection->getDisplayName(),
                $name,
            ))
                ->identifier('boringO11yWirestan.lockedPublicProperty')
                ->line($propertyContext['line'])
                ->build();
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
     * Walk every non-seed method once, collecting the names of mutated public
     * properties and detecting any opaque mass-assignment that defeats static
     * proof of immutability.
     *
     * @return array{opaque: bool, mutated: list<string>}
     */
    private function analyseMutationsOutsideSeedMethods(Class_ $class): array
    {
        $finder = new NodeFinder;
        $mutated = [];
        $opaque = false;

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof ClassMethod) {
                continue;
            }
            if (\in_array($stmt->name->toString(), self::SEED_METHODS, true)) {
                continue;
            }
            if ($stmt->stmts === null) {
                continue;
            }

            $nodes = $finder->find(
                $stmt->stmts,
                fn (Node $node) => $this->isMutatingNode($node) || $this->isMassAssignmentCall($node),
            );

            foreach ($nodes as $node) {
                if ($node instanceof MethodCall) {
                    // $this->reset('a', 'b') reassigns only the named properties;
                    // any other mass-assignment form is opaque.
                    $names = $this->resetTargetNames($node);
                    if ($names === null) {
                        $opaque = true;
                    } else {
                        foreach ($names as $name) {
                            $mutated[] = $name;
                        }
                    }

                    continue;
                }

                // Dynamic write: $this->{$expr} = … — name computed, can't track.
                if ($this->isDynamicPropertyWrite($node)) {
                    $opaque = true;

                    continue;
                }

                foreach ($this->extractMutatedPropertyNames($node) as $name) {
                    $mutated[] = $name;
                }
            }
        }

        return ['opaque' => $opaque, 'mutated' => array_values(array_unique($mutated))];
    }

    private function isMassAssignmentCall(Node $node): bool
    {
        return $node instanceof MethodCall
            && $node->var instanceof Variable
            && $node->var->name === 'this'
            && $node->name instanceof Node\Identifier
            && \in_array($node->name->toString(), self::OPAQUE_MUTATORS, true);
    }

    /**
     * Property names reset by `$this->reset('a', 'b')` / `reset(['a', 'b'])`.
     * Returns null when the call is opaque: a different mass-assign method
     * (fill/mergeData/resetExcept), no-arg reset() (resets all), or non-literal
     * arguments.
     *
     * @return list<string>|null
     */
    private function resetTargetNames(MethodCall $call): ?array
    {
        if (! $call->name instanceof Node\Identifier || $call->name->toString() !== 'reset') {
            return null;
        }
        if ($call->args === []) {
            return null;
        }

        $names = [];
        foreach ($call->args as $arg) {
            if (! $arg instanceof Node\Arg) {
                return null; // variadic / named-arg spread — can't enumerate
            }

            $value = $arg->value;
            if ($value instanceof String_) {
                $names[] = $value->value;

                continue;
            }
            if ($value instanceof Array_) {
                foreach ($value->items as $item) {
                    if ($item === null || ! $item->value instanceof String_) {
                        return null;
                    }
                    $names[] = $item->value->value;
                }

                continue;
            }

            return null; // dynamic argument
        }

        return $names;
    }

    private function isDynamicPropertyWrite(Node $node): bool
    {
        $target = $this->mutationTarget($node);
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }

        return $target instanceof PropertyFetch
            && $target->var instanceof Variable
            && $target->var->name === 'this'
            && ! $target->name instanceof Node\Identifier;
    }

    private function isMutatingNode(Node $node): bool
    {
        return $node instanceof Assign
            || $node instanceof AssignOp
            || $node instanceof AssignRef
            || $node instanceof PreInc
            || $node instanceof PreDec
            || $node instanceof PostInc
            || $node instanceof PostDec;
    }

    /**
     * The assignment target of a mutating node. The instanceof arms narrow the
     * type for PHPStan (all of these expose a public `$var`).
     */
    private function mutationTarget(Node $node): ?Node
    {
        return match (true) {
            $node instanceof Assign,
            $node instanceof AssignOp,
            $node instanceof AssignRef,
            $node instanceof PreInc,
            $node instanceof PreDec,
            $node instanceof PostInc,
            $node instanceof PostDec => $node->var,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function extractMutatedPropertyNames(Node $node): array
    {
        $target = $this->mutationTarget($node);

        return $target === null ? [] : $this->propertyNamesFromTarget($target);
    }

    /**
     * Resolve `$this->prop` names from an assignment target, descending into
     * `list()` / `[$a, $b]` destructuring and unwrapping `$this->prop[$k]`.
     *
     * @return list<string>
     */
    private function propertyNamesFromTarget(Node $target): array
    {
        if ($target instanceof List_ || $target instanceof Array_) {
            $names = [];
            foreach ($target->items as $item) {
                if ($item === null) {
                    continue; // skipped slot: [, $b] = …
                }
                foreach ($this->propertyNamesFromTarget($item->value) as $name) {
                    $names[] = $name;
                }
            }

            return $names;
        }

        // Unwrap array-write: $this->prop[$k] = ...
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }

        if (! $target instanceof PropertyFetch) {
            return [];
        }
        if (! $target->var instanceof Variable || $target->var->name !== 'this') {
            return [];
        }
        if (! $target->name instanceof Node\Identifier) {
            return [];
        }

        return [$target->name->toString()];
    }

    /**
     * Whether the property is typed as a Livewire\Form subtype, resolved via
     * reflection so subclasses in any namespace are matched.
     */
    private function isFormProperty(ClassReflection $classReflection, string $name): bool
    {
        if (! $classReflection->hasNativeProperty($name)) {
            return false;
        }

        $type = $classReflection->getNativeProperty($name)->getReadableType();

        return (new ObjectType(self::LIVEWIRE_FORM_FQCN))->isSuperTypeOf($type)->yes();
    }

    private function hasLockedAttribute(Property $property): bool
    {
        foreach ($property->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() === self::LOCKED_ATTRIBUTE_FQCN) {
                    return true;
                }
            }
        }

        return false;
    }
}
