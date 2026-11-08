<?php

declare(strict_types=1);

namespace Livewire;

abstract class Component
{
    public function render(): mixed
    {
        return null;
    }

    public function dispatch(string $event, mixed ...$params): mixed
    {
        return null;
    }
}
