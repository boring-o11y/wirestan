<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

/**
 * No render(), so Livewire uses livewire/locked-bound-by-convention-component.blade.php.
 */
class LockedBoundByConventionComponent extends Component
{
    public string $title = '';

    public string $search = '';

    public array $rows = [];

    public string $singleQuoted = '';

    public string $viaSet = '';

    public bool $viaToggle = false;

    public string $viaWireSet = '';

    public string $viaAssignment = '';

    public array $viaNestedAssignment = [];

    public string $viaAlpineModel = '';

    public string $viaEntangle = '';

    public string $viaEntangleDirective = '';

    public int $viaEventExpression = 0;

    public mixed $viaUpload = null;

    public string $commentedOut = '';

    public string $parentsProperty = '';

    public string $onlyDisplayed = '';

    public int $bookingId = 0;
}
