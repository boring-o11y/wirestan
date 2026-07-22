<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class ResetByNameComponent extends Component
{
    public int $bookingId = 0;

    public string $search = '';

    public function mount(int $bookingId): void
    {
        $this->bookingId = $bookingId;
    }

    public function clearSearch(): void
    {
        // Resets only the named property, so $search is mutated but the rest
        // of the component (e.g. $bookingId) must still be checked.
        $this->reset('search');
    }
}
