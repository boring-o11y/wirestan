<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SharedComputedCacheComponent extends Component
{
    public string $search = '';

    #[Locked]
    public int $tenantId = 0;

    #[Computed(cache: true)]
    public function viaAuthHelper(): mixed
    {
        return auth()->id();
    }

    #[Computed(cache: true)]
    public function viaFacade(): mixed
    {
        return Auth::id();
    }

    #[Computed(cache: true)]
    public function viaRequest(): mixed
    {
        return request()->ip();
    }

    #[Computed(cache: true)]
    public function viaSession(): mixed
    {
        return session('team');
    }

    #[Computed(cache: true)]
    public function viaGate(): mixed
    {
        return Gate::allows('admin');
    }

    #[Computed(cache: true)]
    public function viaProperty(): string
    {
        return $this->search;
    }

    #[Computed(cache: true)]
    public function viaLockedProperty(): int
    {
        return $this->tenantId;
    }

    #[Computed(cache: true)]
    public function viaHelperMethod(): mixed
    {
        return $this->currentUserId();
    }

    #[Computed(cache: true)]
    public function viaOtherComputed(): mixed
    {
        return $this->viaAuthHelper;
    }

    #[Computed(cache: true, key: 'shared')]
    public function viaNamedKey(): mixed
    {
        return auth()->id();
    }

    #[Computed(persist: true, key: 'shared')]
    public function persistWithKey(): mixed
    {
        return auth()->id();
    }

    #[Computed(true, 3600, false, 'shared')]
    public function persistWithPositionalKey(): mixed
    {
        return auth()->id();
    }

    #[Computed(cache: true)]
    public function multipleReads(): string
    {
        return auth()->id() . $this->search;
    }

    // Livewire uses `key` as a literal string: it doesn't substitute {search}.
    #[Computed(cache: true, key: 'posts-{search}')]
    public function viaPlaceholderKey(): string
    {
        return $this->search;
    }

    protected function currentUserId(): mixed
    {
        return auth()->id();
    }
}

class StalePersistedComputedComponent extends Component
{
    public string $search = '';

    #[Computed(persist: true)]
    public function results(): string
    {
        return $this->search;
    }

    #[Computed(persist: true)]
    public function viaHelper(): string
    {
        return $this->term();
    }

    protected function term(): string
    {
        return $this->search;
    }
}
