<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class LockedUpdateHooksComponent extends Component
{
    public string $title = '';

    public string $first_name = '';

    public array $items = [];

    public int $bookingId = 0;

    public function updatedTitle(string $value): void {}

    public function updatingFirstName(string $value): void {}

    // Livewire calls this for an update to `items.name`.
    public function updatedItemsName(string $value): void {}
}
