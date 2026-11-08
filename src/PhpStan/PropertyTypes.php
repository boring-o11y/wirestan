<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

/**
 * What a component property's declared type means to Livewire.
 */
final class PropertyTypes
{
    private const FORM = 'Livewire\\Form';

    private const MODEL = 'Illuminate\\Database\\Eloquent\\Model';

    private const ELOQUENT_COLLECTION = 'Illuminate\\Database\\Eloquent\\Collection';

    /**
     * Livewire keeps these in its own synthesizers and manages their state itself.
     */
    public static function isForm(ClassReflection $class, string $property): bool
    {
        return self::isDeclaredAs($class, $property, self::FORM);
    }

    /**
     * Livewire's synthesizers refuse client writes to these. Their snapshot holds only
     * the class and keys, so `set('post', 2)` keeps the record and `set('post.id', 2)`
     * throws. Only the native type counts: an untyped property can hold anything.
     */
    public static function isClientProofModelState(ClassReflection $class, string $property): bool
    {
        return self::isDeclaredAs($class, $property, self::MODEL)
            || self::isDeclaredAs($class, $property, self::ELOQUENT_COLLECTION);
    }

    private static function isDeclaredAs(ClassReflection $class, string $property, string $expected): bool
    {
        if (! $class->hasNativeProperty($property)) {
            return false;
        }

        $type = TypeCombinator::removeNull($class->getNativeProperty($property)->getNativeType());

        return (new ObjectType($expected))->isSuperTypeOf($type)->yes();
    }
}
