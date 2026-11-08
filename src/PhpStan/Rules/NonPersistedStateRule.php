<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\PropertyWrites;
use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Only public properties travel in the snapshot. mount() runs on the first request
 * alone, so a protected or private property it sets is gone on the next one, leaving
 * the default value or an uninitialized-property Error.
 *
 * A write anywhere that runs on later requests is taken as re-seeding the property,
 * which covers boot(), booted(), hydrate() and lazy `??=` patterns in helpers.
 *
 * @implements Rule<ClassMethodsNode>
 */
final class NonPersistedStateRule implements Rule
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

        $methods = ComponentMethods::fromNode($node);
        $later = $this->methodsRunningAfterMount($methods, $class);

        /** @var array<string, array{method: ClassMethod, line: int}> $seeded */
        $seeded = [];
        /** @var array<string, string> $reads first method reading each property */
        $reads = [];
        /** @var array<string, true> $rewritten */
        $rewritten = [];

        foreach ($methods->all() as $method) {
            $writes = PropertyWrites::in($method);

            // `$this->{$name} = …` could be re-seeding any of them.
            if ($writes->dynamic) {
                return [];
            }

            $isLater = isset($later[$method->name->toLowerString()]);

            foreach ($writes->named as $write) {
                if ($write->nested) {
                    continue;
                }
                if ($isLater) {
                    $rewritten[$write->name] = true;
                } else {
                    $seeded[$write->name] ??= ['method' => $method, 'line' => $write->line];
                }
            }

            if ($isLater) {
                foreach (ComponentMethods::findIn($method, static fn (Node $node): bool => $node instanceof PropertyFetch) as $fetch) {
                    $name = ComponentMethods::thisMember($fetch);

                    // A fetch that's really a write doesn't matter here: it puts the
                    // property in $rewritten, which rules it out anyway.
                    if ($name !== null) {
                        $reads[$name] ??= $method->name->toString();
                    }
                }
            }
        }

        $errors = [];

        foreach ($seeded as $name => $seed) {
            if (isset($rewritten[$name]) || ! isset($reads[$name]) || ! $this->isOwnNonPublicProperty($class, $name)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Livewire component %s sets non-public property $%s in %s() and reads it in %s(), but only public properties survive between requests, so the value is lost after the first one. Set it in boot() or hydrate(), which run on every request, or make it public with #[Locked] if it holds plain data.',
                $class->getDisplayName(),
                $name,
                $seed['method']->name->toString(),
                $reads[$name],
            ))
                ->identifier('boringO11yWirestan.nonPersistedState')
                ->line($methods->reportingLineWithin($seed['method'], $seed['line']))
                ->build();
        }

        return $errors;
    }

    /**
     * Every method except mount hooks and the non-public helpers only they call. A
     * non-public method nothing here calls is assumed to be called from outside: by
     * Livewire, like rules(), or by a subclass.
     *
     * @return array<string, ClassMethod> keyed by lowercase name
     */
    private function methodsRunningAfterMount(ComponentMethods $methods, ClassReflection $class): array
    {
        $called = [];
        foreach ($methods->all() as $method) {
            foreach (ComponentMethods::findIn($method, static fn (Node $node): bool => ComponentMethods::thisMember($node) !== null) as $member) {
                $called[strtolower((string) ComponentMethods::thisMember($member))] = true;
            }
        }

        $later = [];
        foreach ($methods->all() as $method) {
            $name = $method->name->toString();

            if (strcasecmp($name, 'mount') === 0 || ComponentMethods::isTraitHook($name, $class, 'mount')) {
                continue;
            }

            if ($method->isPublic() || ! isset($called[$method->name->toLowerString()])) {
                foreach ($methods->reachableFrom($method) as $reached) {
                    $later[$reached->name->toLowerString()] = $reached;
                }
            }
        }

        return $later;
    }

    private function isOwnNonPublicProperty(ClassReflection $class, string $name): bool
    {
        if (! $class->hasNativeProperty($name)) {
            return false;
        }

        $property = $class->getNativeProperty($name);

        return ! $property->isPublic()
            && ! $property->isStatic()
            && $property->getDeclaringClass()->getName() === $class->getName();
    }
}
