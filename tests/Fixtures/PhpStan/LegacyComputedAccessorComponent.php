<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Computed;
use Livewire\Component;

trait ProvidesLegacyAccessor
{
    public function getFromTraitProperty(): string
    {
        return 'secret';
    }
}

class LegacyComputedAccessorComponent extends Component
{
    use ProvidesLegacyAccessor;

    public function getTotalProperty(): int
    {
        return 1;
    }

    public function getOptionalArgumentProperty(int $limit = 10): int
    {
        return $limit;
    }

    #[Computed]
    public function getMarkedComputedProperty(): int
    {
        return 1;
    }

    public function getRequiredArgumentProperty(int $id): int
    {
        return $id;
    }

    public function getProperty(): int
    {
        return 1;
    }

    public function getProperties(): array
    {
        return [];
    }

    protected function getProtectedProperty(): int
    {
        return 1;
    }

    public static function getStaticProperty(): int
    {
        return 1;
    }
}

class PlainClassWithLegacyAccessor
{
    public function getTotalProperty(): int
    {
        return 1;
    }
}
