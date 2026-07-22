<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class DestructuringMutationComponent extends Component
{
    public int $bookingId = 0;

    public string $name = '';

    public function mount(int $bookingId): void
    {
        $this->bookingId = $bookingId;
    }

    public function swap(array $data): void
    {
        // Reassigned via list destructuring — counts as a user-driven mutation,
        // so neither property requires #[Locked].
        [$this->bookingId, $this->name] = $data;
    }
}
