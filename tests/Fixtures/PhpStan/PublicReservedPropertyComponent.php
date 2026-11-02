<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class PublicReservedPropertyComponent extends Component
{
    public array $rules = ['title' => 'required'];

    public array $messages = [];

    public array $validationAttributes = [];

    public array $validationCustomValues = [];

    public $listeners = [];

    public array $queryString = [];

    public string $paginationTheme = 'tailwind';

    public string $title = '';
}
