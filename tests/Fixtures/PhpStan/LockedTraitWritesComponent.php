<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

trait WritesAndValidatesFromTrait
{
    public function rename(string $value): void
    {
        $this->writtenByTrait = $value;
    }

    public function updatedHookedByTrait(): void {}

    protected function rules(): array
    {
        return ['validatedByTrait' => 'required'];
    }
}

class LockedTraitWritesComponent extends Component
{
    use WritesAndValidatesFromTrait;

    public string $writtenByTrait = '';

    public string $hookedByTrait = '';

    public string $validatedByTrait = '';

    public int $bookingId = 0;
}
