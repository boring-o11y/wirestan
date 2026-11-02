<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Url;
use Livewire\Component;

class UrlBoundComponent extends Component
{
    #[Url]
    public string $search = '';
}
