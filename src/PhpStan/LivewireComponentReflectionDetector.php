<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

use PHPStan\Reflection\ClassReflection;

/**
 * Reflection-based detector. Walks the parent chain of a class to determine
 * whether it ultimately extends Livewire\Component. Cached per ClassReflection
 * (PHPStan reuses ClassReflection instances within a run).
 */
final class LivewireComponentReflectionDetector
{
    private const LIVEWIRE_COMPONENT_FQCN = 'Livewire\\Component';

    /**
     * @var array<string, bool>
     */
    private array $cache = [];

    public function isLivewireComponent(ClassReflection $classReflection): bool
    {
        $name = $classReflection->getName();

        if (\array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        if ($classReflection->isInterface() || $classReflection->isTrait()) {
            return $this->cache[$name] = false;
        }

        foreach ($classReflection->getAncestors() as $ancestor) {
            if ($ancestor->getName() === self::LIVEWIRE_COMPONENT_FQCN) {
                return $this->cache[$name] = true;
            }
        }

        return $this->cache[$name] = false;
    }
}
