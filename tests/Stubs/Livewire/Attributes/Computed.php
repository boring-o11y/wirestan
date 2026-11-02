<?php

declare(strict_types=1);

namespace Livewire\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Computed
{
    public function __construct(
        public bool $persist = false,
        public int $seconds = 3600,
        public bool $cache = false,
        public ?string $key = null,
        public mixed $tags = null,
    ) {}
}
