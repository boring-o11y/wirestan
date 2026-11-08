<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Reactive;
use Livewire\Component;

trait WritesReactiveFromTrait
{
    public function fromTrait(): void
    {
        $this->count = 2;
    }
}

class ReactiveMutationComponent extends Component
{
    use WritesReactiveFromTrait;

    #[Reactive]
    public int $count = 0;

    #[Reactive]
    public array $items = [];

    #[Reactive]
    public ?BindablePost $post = null;

    public string $own = '';

    // Overwritten by the parent's value straight after, so nothing throws.
    public function __construct()
    {
        $this->count = 1;
    }

    public function mount(): void
    {
        $this->count = 1;
    }

    public function boot(): void
    {
        $this->count = 1;
    }

    public function hydrate(): void
    {
        $this->count = 1;
    }

    public function render(): mixed
    {
        $this->count = 1;

        return null;
    }

    public function increment(): void
    {
        $this->count++;
    }

    public function appendItem(): void
    {
        $this->items[] = 1;
    }

    public function removeItem(): void
    {
        unset($this->items[0]);
    }

    public function sortItems(): void
    {
        sort($this->items);
    }

    public function renamePost(): void
    {
        $this->post->title = 'x';
    }

    public function resetCount(): void
    {
        $this->reset('count');
    }

    public function fillCount(): void
    {
        $this->fill(['count' => 1]);
    }

    public function resetEverything(): void
    {
        $this->reset();
    }

    public function resetAllButOwn(): void
    {
        $this->resetExcept('own');
    }

    // Runs after Livewire's comparison, so nothing throws.
    public function dehydrate(): void
    {
        $this->count = 1;
    }

    public function resetOwn(): void
    {
        $this->reset('own');
    }

    public function resetAroundThem(): void
    {
        $this->resetExcept(['count', 'items', 'post']);
    }

    public function fillOwn(array $data): void
    {
        $this->fill(['own' => 'x']);
        $this->fill($data);
    }

    public function readOnly(): int
    {
        $this->own = 'x';

        return $this->count + \count($this->items);
    }
}

class NoReactivePropertiesComponent extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}
