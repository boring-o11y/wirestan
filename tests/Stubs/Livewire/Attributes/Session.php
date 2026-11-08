<?php

declare(strict_types=1);

namespace Livewire\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Session
{
    public function __construct(public ?string $key = null) {}
}
