<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Locked;
use Livewire\Component;

class ProtectedReservedPropertyComponent extends Component
{
    protected array $rules = ['title' => 'required'];

    protected $listeners = [];

    #[Locked]
    public string $paginationTheme = 'tailwind';

    public static array $messages = [];

    public string $title = '';
}

class PlainClassWithPublicRules
{
    public array $rules = [];
}
