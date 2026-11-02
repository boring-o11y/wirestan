<?php

declare(strict_types=1);

namespace Livewire\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Url
{
    public function __construct(public ?string $as = null) {}
}
