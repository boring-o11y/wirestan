<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Modelable;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Only $bookingId is server-controlled. Each of the others is either meant to be set
 * by the client, so locking it throws CannotUpdateLockedPropertyException, or the
 * client can't write it at all.
 */
class LockedAttributeInputsComponent extends Component
{
    #[Validate('required|email')]
    public string $validated = '';

    #[Rule('required')]
    public string $legacyRule = '';

    #[Modelable]
    public string $modelable = '';

    #[Reactive]
    public int $reactive = 0;

    #[Session]
    public string $sessioned = '';

    public int $bookingId = 0;

    public function mount(int $bookingId): void
    {
        $this->bookingId = $bookingId;
    }
}
