<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

/**
 * One write to a named `$this->` property.
 */
final class PropertyWrite
{
    /**
     * @param  bool  $nested  Whether it changes an object the property holds, as in
     *                        `$this->post->title = 'x'`, rather than the property.
     * @param  bool  $viaCall  Whether a call such as `$this->reset('name')` made it.
     */
    public function __construct(
        public readonly string $name,
        public readonly int $line,
        public readonly bool $nested = false,
        public readonly bool $viaCall = false,
    ) {}
}
