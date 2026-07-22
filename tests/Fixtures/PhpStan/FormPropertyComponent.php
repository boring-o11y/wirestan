<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class FormPropertyComponent extends Component
{
    public AllowedLoginForm $form;

    public function save(): void
    {
        // Form fields are updated via wire:model="form.email"; the root $form
        // is never reassigned here, yet must NOT be required to be #[Locked].
    }
}
