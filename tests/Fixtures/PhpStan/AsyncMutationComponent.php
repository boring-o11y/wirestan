<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Async;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AsyncMutationComponent extends Component
{
    public int $count = 0;

    public array $items = [];

    public BindablePost $post;

    public AllowedLoginForm $form;

    protected int $internal = 0;

    public static int $shared = 0;

    #[Async]
    public function increment(): void
    {
        $this->count++;
    }

    #[Async]
    public function throughHelper(): void
    {
        $this->addItem();
    }

    private function addItem(): void
    {
        $this->items[] = 1;
    }

    #[Async]
    public function writesTwo(): void
    {
        $this->count = 1;
        $this->items = [];
        $this->count = 2;
    }

    #[Async]
    public function resetsEverything(): void
    {
        $this->reset();
    }

    #[Async]
    public function resetsByName(): void
    {
        $this->reset('count');
    }

    #[Async]
    public function fills(array $data): void
    {
        $this->fill($data);
    }

    #[Async]
    public function writesFormField(): void
    {
        $this->form->email = 'x';
    }

    #[Async]
    public function replacesModel(): void
    {
        $this->post = new BindablePost;
    }

    #[Async]
    public function onlyReads(): int
    {
        return $this->count;
    }

    #[Async]
    public function writesWhatIsNotInTheSnapshot(): void
    {
        $this->internal++;
        self::$shared++;
        $this->post->title = 'x';
    }

    #[Async]
    public function bustsComputed(): void
    {
        unset($this->rows);
    }

    public function notAsync(): void
    {
        $this->count++;
    }

    #[Computed]
    public function rows(): array
    {
        return [];
    }
}
