<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

/**
 * A `$this->` call that writes several public properties, such as `reset()` or
 * `fill()`, where the targets aren't all spelled out at the call.
 */
final class MassWrite
{
    /**
     * @param  list<string>|null  $except  What it leaves alone when it writes every other
     *                                     public property, or null when it doesn't.
     * @param  list<string>  $names  Properties known to be written by name.
     */
    public function __construct(
        public readonly string $call,
        public readonly int $line,
        public readonly ?array $except = null,
        public readonly array $names = [],
    ) {}

    /**
     * Whether the call definitely writes this property. A call whose targets can't
     * be read, such as `fill($data)`, covers nothing here.
     */
    public function covers(string $property): bool
    {
        if (\in_array($property, $this->names, true)) {
            return true;
        }

        return $this->except !== null && ! \in_array($property, $this->except, true);
    }
}
