<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AllowedComputedCacheComponent extends Component
{
    public string $search = '';

    #[Locked]
    public int $tenantId = 0;

    public ?BindablePost $post = null;

    protected string $label = 'static';

    #[Computed]
    public function notCached(): mixed
    {
        return auth()->id() . $this->search;
    }

    #[Computed(cache: false)]
    public function cachingSwitchedOff(): mixed
    {
        return auth()->id();
    }

    #[Computed(cache: true)]
    public function pure(): int
    {
        return 1;
    }

    #[Computed(cache: true, seconds: 60)]
    public function readsNonPublicState(): string
    {
        return $this->label;
    }

    // persist without a key is scoped to one component instance, not shared.
    #[Computed(persist: true)]
    public function perInstanceUser(): mixed
    {
        return auth()->id();
    }

    // persist takes precedence over cache.
    #[Computed(persist: true, cache: true)]
    public function persistWins(): mixed
    {
        return auth()->id();
    }

    #[Computed(persist: true)]
    public function readsLockedProperty(): int
    {
        return $this->tenantId;
    }

    #[Computed(persist: true)]
    public function readsModelProperty(): ?BindablePost
    {
        return $this->post;
    }

    #[Computed(persist: true)]
    public function readsProtectedProperty(): string
    {
        return $this->label;
    }

    #[Computed(persist: true)]
    public function bustedOnChange(): string
    {
        return $this->search;
    }

    public function updatedSearch(): void
    {
        unset($this->bustedOnChange);
    }
}

class PlainClassWithComputed
{
    #[Computed(cache: true)]
    public function user(): mixed
    {
        return auth()->id();
    }
}
