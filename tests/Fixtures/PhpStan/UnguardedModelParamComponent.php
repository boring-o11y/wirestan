<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Authorize;
use Livewire\Attributes\On;
use Livewire\Component;

trait DeletesPostsWithoutGuard
{
    public function deleteFromTrait(BindablePost $post): void
    {
    }
}

class UnguardedModelParamComponent extends Component
{
    use DeletesPostsWithoutGuard;

    public function delete(BindablePost $post): void
    {
    }

    public function nullable(?BindablePost $post = null): void
    {
    }

    public function twoModels(BindablePost $post, BindablePost $other): void
    {
    }

    #[On('post-removed')]
    public function handleRemoved(BindablePost $post): void
    {
    }

    // Named like a hook, but #[On] handlers are reached through __dispatch.
    #[On('post-updated')]
    public function updatedFromEvent(BindablePost $post): void
    {
    }

    // #[Authorize] only runs when the client calls that method directly.
    public function viaAttributeOnHelper(BindablePost $post): void
    {
        $this->authorizedHelper($post);
    }

    #[Authorize('delete', 'post')]
    protected function authorizedHelper(BindablePost $post): void
    {
    }

    public function identityCheckWithoutAuth(BindablePost $post): void
    {
        if (! $post->owner->is($post->author)) {
            return;
        }
    }
}

// mount() only runs on the first request, so this protects nothing afterwards.
class MountOnlyGuardedComponent extends Component
{
    public function mount(): void
    {
        $this->authorize('view-posts');
    }

    public function delete(BindablePost $post): void
    {
    }
}
