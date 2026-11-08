<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Form;

trait ComputedFromTrait
{
    #[Computed]
    public function fromTrait(int $required): int
    {
        return $required;
    }

    public function callsFromTrait(): int
    {
        return $this->withDefault();
    }
}

class ComputedMisuseComponent extends Component
{
    use ComputedFromTrait;

    public string $shadowedPublic = '';

    protected string $shadowedProtected = '';

    private string $shadowedPrivate = '';

    public static string $staticSameName = '';

    public string $seen = '';

    #[Computed]
    public function required(int $id, object $service): int
    {
        return $id;
    }

    #[Computed]
    public function withDefault(int $limit = 3, int ...$rest): int
    {
        return $limit;
    }

    #[Computed]
    public function shadowedPublic(): string
    {
        return 'x';
    }

    #[Computed]
    public function shadowedProtected(): string
    {
        return 'x';
    }

    #[Computed]
    public function shadowedPrivate(): string
    {
        return 'x';
    }

    #[Computed]
    public function staticSameName(): string
    {
        return 'x';
    }

    public function callsComputed(): int
    {
        return $this->withDefault() + $this->withDefault;
    }

    public function passesAnArgument(): int
    {
        return $this->withDefault(5);
    }

    public function takesACallable(): callable
    {
        return $this->withDefault(...);
    }

    public function readsIt(): int
    {
        return $this->withDefault;
    }

    public function plainMethod(): int
    {
        return $this->notComputed();
    }

    public function notComputed(): int
    {
        return 1;
    }
}

class ComputedOnFormObject extends Form
{
    #[Computed]
    public function upper(): string
    {
        return 'x';
    }

    public function plainHelper(): string
    {
        return 'x';
    }
}
