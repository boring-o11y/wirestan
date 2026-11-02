<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Authorize;
use Livewire\Attributes\Computed;
use Livewire\Component;

trait ProvidesAuthorizeTarget
{
    public ?BindablePost $fromTrait = null;
}

class AllowedAuthorizeTargetComponent extends Component
{
    use ProvidesAuthorizeTarget;

    private const TARGET = 'post';

    public ?BindablePost $post = null;

    #[Authorize('view-dashboard')]
    public function noArgument(): void
    {
    }

    #[Authorize('create', BindablePost::class)]
    public function classTarget(): void
    {
    }

    #[Authorize('update', 'post')]
    public function propertyTarget(): void
    {
    }

    #[Authorize('update', 'post.owner')]
    public function nestedPropertyTarget(): void
    {
    }

    #[Authorize('update', 'fromTrait')]
    public function traitPropertyTarget(): void
    {
    }

    #[Authorize('update', self::TARGET)]
    public function constantTarget(): void
    {
    }

    #[Authorize('delete', 'comment')]
    public function parameterTarget(BindablePost $comment): void
    {
    }

    #[Authorize('update', 'current')]
    public function computedTarget(): void
    {
    }

    #[Authorize('update', 'legacy')]
    public function legacyAccessorTarget(): void
    {
    }

    #[Authorize('create', [BindablePost::class, 'post'])]
    public function arrayTarget(): void
    {
    }

    #[Computed]
    public function current(): ?BindablePost
    {
        return $this->post;
    }

    public function getLegacyProperty(): ?BindablePost
    {
        return $this->post;
    }
}

class PlainClassWithAuthorize
{
    #[Authorize('update', 'psot')]
    public function typo(): void
    {
    }
}
