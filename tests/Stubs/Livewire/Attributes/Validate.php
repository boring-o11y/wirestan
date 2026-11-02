<?php

declare(strict_types=1);

namespace Livewire\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_ALL)]
final class Validate
{
    public function __construct(public mixed $rule = null) {}
}
