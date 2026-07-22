<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class FillMutationComponent extends Component
{
    public int $bookingId = 0;

    public function mount(int $bookingId): void
    {
        $this->bookingId = $bookingId;
    }

    public function save(array $data): void
    {
        // Mass-assignment can touch any public property, so immutability can't
        // be proven — the rule must not flag $bookingId here.
        $this->fill($data);
    }
}
