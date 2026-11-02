<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class CustomGuardComponent extends Component
{
    public function delete(BindablePost $post): void
    {
        $this->ensureOwnership($post);
    }
}
