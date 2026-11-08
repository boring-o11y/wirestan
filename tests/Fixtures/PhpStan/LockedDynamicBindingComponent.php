<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

/**
 * The view computes the name it binds, so it could be any of these.
 */
class LockedDynamicBindingComponent extends Component
{
    public string $first = '';

    public string $second = '';

    public function render(): mixed
    {
        return view('custom.dynamic');
    }
}
