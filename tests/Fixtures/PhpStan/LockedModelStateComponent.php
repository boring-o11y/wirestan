<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * Livewire ignores client writes to these: set('post', 2) keeps the original record
 * and set('post.id', 2) throws. Locking them isn't needed.
 */
class LockedModelStateComponent extends Component
{
    public BindablePost $post;

    public ?BindablePost $maybePost = null;

    public Model $anyModel;

    public Collection $posts;

    public int $bookingId = 0;
}
