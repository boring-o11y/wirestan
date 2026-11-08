<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class LockedRenderedViewComponent extends Component
{
    public string $inView = '';

    public string $inInclude = '';

    public string $inNestedInclude = '';

    public string $inConditionalInclude = '';

    public string $inUnlessInclude = '';

    public string $inFirstInclude = '';

    public string $inEachView = '';

    public int $bookingId = 0;

    public function render(): mixed
    {
        return view('custom.rendered', ['a' => 1])->layout('layouts.app');
    }
}
