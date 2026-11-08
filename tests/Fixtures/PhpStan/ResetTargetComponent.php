<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Computed;
use Livewire\Component;

trait ResetsFromTrait
{
    public function resetFromTrait(): void
    {
        $this->reset('unknownFromTrait');
    }
}

class ResetTargetComponent extends Component
{
    use ResetsFromTrait;

    public string $withDefault = '';

    public $untyped;

    public ?string $nullableDefault = null;

    public string $typedNoDefault;

    public ?BindablePost $nullableNoDefault;

    public AllowedLoginForm $form;

    public array $items = [];

    protected string $protectedValue = '';

    private string $privateValue = '';

    public function unknown(): void
    {
        $this->reset('nope');
    }

    public function unknownInArray(): void
    {
        $this->reset(['withDefault', 'nope']);
    }

    public function unknownNested(): void
    {
        $this->reset('nope.field');
    }

    public function computedName(): void
    {
        $this->reset('rows');
    }

    public function nonPublic(): void
    {
        $this->reset('protectedValue');
        $this->reset('privateValue');
    }

    public function typedWithoutDefault(): void
    {
        $this->reset('typedNoDefault', 'nullableNoDefault');
    }

    public function pullsThem(): void
    {
        $this->pull('nope');
        $this->pull('typedNoDefault');
    }

    public function safeTargets(): void
    {
        $this->reset('withDefault', 'untyped', 'nullableDefault', 'items');
        $this->reset(['form', 'form.email', 'items.a']);
        $this->pull('withDefault');
    }

    public function reassignedAfterwards(): void
    {
        $this->reset('typedNoDefault');
        $this->typedNoDefault = 'again';
    }

    public function notLiteral(string $name, array $names): void
    {
        $this->reset($name);
        $this->reset(...$names);
    }

    public function ignoresUnknownExceptions(): void
    {
        $this->resetExcept(['typedNoDefault', 'nullableNoDefault', 'nope']);
    }

    #[Computed]
    public function rows(): array
    {
        return [];
    }
}

class ResetEverythingComponent extends Component
{
    public string $withDefault = '';

    public BindablePost $post;

    public AllowedLoginForm $form;

    protected string $notPublicNoDefault;

    public function everything(): void
    {
        $this->reset();
    }

    public function pullEverything(): void
    {
        $this->pull();
    }

    public function allButTheForm(): void
    {
        $this->resetExcept('form');
    }

    public function allButThePost(): void
    {
        $this->resetExcept('post');
    }

    public function everythingThenReassign(): void
    {
        $this->reset();
        $this->post = new BindablePost;
    }
}

class ResetSetByConstructorComponent extends Component
{
    public string $set;

    public function __construct()
    {
        $this->set = 'x';
    }

    public function clear(): void
    {
        $this->reset('set');
    }

    public function clearAll(): void
    {
        $this->reset();
    }
}
