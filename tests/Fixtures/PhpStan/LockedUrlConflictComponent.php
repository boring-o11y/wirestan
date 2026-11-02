<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Form;

class LockedUrlConflictComponent extends Component
{
    #[Locked]
    #[Url]
    public int $tenantId = 0;

    #[Locked, Url(as: 'team')]
    public int $teamId = 0;

    #[Locked]
    public int $lockedOnly = 0;

    #[Url]
    public string $urlOnly = '';
}

class LockedUrlConflictForm extends Form
{
    #[Locked]
    #[Url]
    public int $ownerId = 0;
}

class PlainClassWithLockedUrl
{
    #[Locked]
    #[Url]
    public int $id = 0;
}
