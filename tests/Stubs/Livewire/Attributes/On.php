<?php

declare(strict_types=1);

namespace Livewire\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD)]
final class On
{
    public function __construct(public string $event) {}
}
