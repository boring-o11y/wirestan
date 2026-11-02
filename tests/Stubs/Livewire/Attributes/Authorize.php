<?php

declare(strict_types=1);

namespace Livewire\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD)]
final class Authorize
{
    public function __construct(public mixed $ability, public array|string|null $argument = null) {}
}
