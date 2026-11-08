<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class LockedMissingViewComponent extends Component
{
    public string $title = '';

    public function render(): mixed
    {
        return view('does.not.exist');
    }
}

class LockedNamespacedViewComponent extends Component
{
    public string $title = '';

    public function render(): mixed
    {
        return view('package::form');
    }
}

class LockedComputedViewNameComponent extends Component
{
    public string $title = '';

    public function render(): mixed
    {
        return view($this->viewName());
    }

    private function viewName(): string
    {
        return 'custom.nested';
    }
}

class LockedPartlyResolvedViewComponent extends Component
{
    public string $bound = '';

    public string $title = '';

    public function render(): mixed
    {
        return view('custom.includes-unknown');
    }
}
