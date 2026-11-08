<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

/**
 * Every property but $bookingId is changed by an action, in ways that aren't an
 * assignment to the property itself.
 */
class LockedInPlaceWritesComponent extends Component
{
    public array $unsetElement = [];

    public array $sorted = [];

    public array $pushed = [];

    public array $byReference = [];

    public array $referenced = [];

    public string $loopTarget = '';

    public object $nestedWrite;

    public string $pulled = '';

    public int $bookingId = 0;

    public function remove(string $key): void
    {
        unset($this->unsetElement[$key]);
    }

    public function order(): void
    {
        sort($this->sorted);
        array_push($this->pushed, 1);
    }

    public function walk(array $values): void
    {
        foreach ($this->byReference as &$value) {
            $value = 1;
        }

        foreach ($values as $this->loopTarget) {
            // assigned by the loop
        }

        $alias = &$this->referenced;
        $alias[] = 1;
    }

    public function change(): void
    {
        $this->nestedWrite->name = 'x';
        $this->pull('pulled');
    }
}
