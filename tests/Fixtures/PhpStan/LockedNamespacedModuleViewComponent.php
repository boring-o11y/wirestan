<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class LockedNamespacedModuleViewComponent extends Component
{
    public string $inView = '';

    public string $inInclude = '';

    public int $bookingId = 0;

    public function render(): mixed
    {
        return view('Shop::checkout.form');
    }
}
