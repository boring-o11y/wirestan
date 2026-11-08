<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Illuminate\Support\Facades\View;
use Livewire\Component;

class LockedInlineViewComponent extends Component
{
    public string $inline = '';

    public int $bookingId = 0;

    public function render(): string
    {
        return <<<'HTML'
            <div><input wire:model="inline"></div>
            HTML;
    }
}

class LockedFacadeViewComponent extends Component
{
    public string $inView = '';

    public int $bookingId = 0;

    public function render(): mixed
    {
        return View::make('custom.nested');
    }
}

class LockedViewHelperMakeComponent extends Component
{
    public string $inNestedInclude = '';

    public int $bookingId = 0;

    public function render(): mixed
    {
        return view()->make('custom.nested');
    }
}
