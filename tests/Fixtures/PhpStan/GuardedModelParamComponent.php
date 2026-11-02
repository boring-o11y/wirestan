<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Authorize;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GuardedModelParamComponent extends Component
{
    #[Authorize('delete', 'post')]
    public function viaAttribute(BindablePost $post): void
    {
    }

    public function viaAuthorize(BindablePost $post): void
    {
        $this->authorize('delete', $post);
    }

    public function viaGate(BindablePost $post): void
    {
        Gate::authorize('delete', $post);
    }

    public function viaAbortUnless(BindablePost $post): void
    {
        abort_unless($post->published, 403);
    }

    public function viaCan(BindablePost $post): void
    {
        if (auth()->user()->cannot('delete', $post)) {
            return;
        }
    }

    public function viaOwnershipComparison(BindablePost $post): void
    {
        if ($post->user_id !== auth()->id()) {
            return;
        }
    }

    public function viaFacadeComparison(BindablePost $post): void
    {
        if (Auth::id() != $post->user_id) {
            return;
        }
    }

    public function viaIsCheck(BindablePost $post): void
    {
        if (! $post->owner->is(Auth::user())) {
            return;
        }
    }

    public function viaIsCheckOnAuthUser(BindablePost $post): void
    {
        if (! auth()->user()->is($post->owner)) {
            return;
        }
    }

    public function viaIsNotOnFacadeUser(BindablePost $post): void
    {
        if (Auth::user()?->isNot($post->owner)) {
            return;
        }
    }

    public function viaException(BindablePost $post): void
    {
        throw new AuthorizationException;
    }

    public function viaHelper(BindablePost $post): void
    {
        $this->ensureOwner($post);
    }

    public function viaNestedHelper(BindablePost $post): void
    {
        $this->loadForOwner($post);
    }

    protected function loadForOwner(BindablePost $post): void
    {
        $this->ensureOwner($post);
    }

    protected function ensureOwner(BindablePost $post): void
    {
        $this->authorize('update', $post);
    }

    // None of these can be reached from the browser with a client-chosen record.
    protected function internal(BindablePost $post): void
    {
    }

    public static function staticHelper(BindablePost $post): void
    {
    }

    public function mount(BindablePost $post): void
    {
    }

    public function updatedPost(BindablePost $post): void
    {
    }

    #[Computed]
    public function related(BindablePost $post): int
    {
        return 1;
    }

    // These don't bind a record at all.
    public function scalarId(int $id): void
    {
    }

    public function untyped($post): void
    {
    }
}

class BootGuardedComponent extends Component
{
    public function boot(): void
    {
        $this->authorize('manage-posts');
    }

    public function delete(BindablePost $post): void
    {
    }
}

class HydrateGuardedComponent extends Component
{
    public function hydrate(): void
    {
        $this->ensureAdmin();
    }

    public function delete(BindablePost $post): void
    {
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()->admin, 403);
    }
}

trait HasPostForm
{
    public function mountHasPostForm(BindablePost $post): void
    {
    }
}

class TraitMountHookComponent extends Component
{
    use HasPostForm;
}

class PlainClassWithModelParam
{
    public function delete(BindablePost $post): void
    {
    }
}
