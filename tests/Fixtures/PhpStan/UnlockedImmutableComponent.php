<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Locked;
use Livewire\Component;

class UnlockedImmutableComponent extends Component
{
    public int $bookingId = 0;

    public string $name = '';

    #[Locked]
    public string $alreadyLocked = '';

    public function mount(int $bookingId, string $name): void
    {
        $this->bookingId = $bookingId;
        $this->name = $name;
    }

    public function rename(string $newName): void
    {
        $this->name = $newName;
    }
}
