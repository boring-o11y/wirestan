<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PHPStan\BetterReflection\Reflection\Adapter\FakeReflectionAttribute;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute;

/**
 * Reads Livewire attributes off parsed nodes. PHPStan's parser resolves attribute
 * names, so comparing against the fully-qualified name honours imports and aliases.
 */
final class LivewireAttributes
{
    public const ASYNC = 'Livewire\\Attributes\\Async';

    public const AUTHORIZE = 'Livewire\\Attributes\\Authorize';

    public const COMPUTED = 'Livewire\\Attributes\\Computed';

    public const LOCKED = 'Livewire\\Attributes\\Locked';

    public const MODELABLE = 'Livewire\\Attributes\\Modelable';

    public const ON = 'Livewire\\Attributes\\On';

    public const REACTIVE = 'Livewire\\Attributes\\Reactive';

    public const RULE = 'Livewire\\Attributes\\Rule';

    public const SESSION = 'Livewire\\Attributes\\Session';

    public const URL = 'Livewire\\Attributes\\Url';

    public const VALIDATE = 'Livewire\\Attributes\\Validate';

    /**
     * @param  array<AttributeGroup>  $groups
     */
    public static function has(array $groups, string ...$names): bool
    {
        foreach ($names as $name) {
            if (self::all($groups, $name) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<AttributeGroup>  $groups
     * @return list<Attribute>
     */
    public static function all(array $groups, string $name): array
    {
        $attributes = [];

        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() === $name) {
                    $attributes[] = $attribute;
                }
            }
        }

        return $attributes;
    }

    /**
     * For attributes read through reflection, which reaches members that come from
     * traits and parent classes.
     *
     * @param  array<ReflectionAttribute|FakeReflectionAttribute>  $attributes
     */
    public static function includes(array $attributes, string ...$names): bool
    {
        foreach ($attributes as $attribute) {
            if (\in_array($attribute->getName(), $names, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The value passed for a constructor parameter, whether by name or position.
     */
    public static function argument(Attribute $attribute, string $name, int $position): ?Expr
    {
        foreach ($attribute->args as $index => $arg) {
            $matches = $arg->name !== null
                ? $arg->name->toString() === $name
                : $index === $position;

            if ($matches) {
                return $arg->value;
            }
        }

        return null;
    }
}
