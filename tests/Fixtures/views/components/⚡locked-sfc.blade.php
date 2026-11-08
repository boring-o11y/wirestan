<?php

use Livewire\Component;

new class extends Component
{
    public string $bound = '';

    public int $bookingId = 0;
};
?>

<div>
    <input wire:model="bound">
</div>
