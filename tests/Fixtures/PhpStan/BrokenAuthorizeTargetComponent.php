<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Authorize;
use Livewire\Component;

class BrokenAuthorizeTargetComponent extends Component
{
    public ?BindablePost $post = null;

    protected ?BindablePost $hidden = null;

    #[Authorize('update', 'psot')]
    public function typo(): void
    {
    }

    #[Authorize('update', 'hidden')]
    public function nonPublicProperty(): void
    {
    }

    #[Authorize('update', 'plainMethod')]
    public function notComputed(): void
    {
    }

    #[Authorize('update', 'App\\Models\\Missing')]
    public function unknownClass(): void
    {
    }

    #[Authorize('create', [BindablePost::class, 'psot'])]
    public function arrayTarget(): void
    {
    }

    #[Authorize(ability: 'update', argument: 'psot')]
    public function namedArgument(): void
    {
    }

    #[Authorize('update', 'post')]
    #[Authorize('delete', 'psot')]
    public function stacked(): void
    {
    }

    #[Authorize('delete', 'comment')]
    public function untypedParameter($comment): void
    {
    }

    public function plainMethod(): void
    {
    }
}
