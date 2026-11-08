<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Component;

class NonPersistedStateComponent extends Component
{
    protected ?int $lost = null;

    private string $lostPrivate = '';

    protected object $lostService;

    protected int $readInRender = 0;

    protected int $readByHelper = 0;

    protected int $readThroughNestedWrite = 0;

    protected int $setByMountHelper = 0;

    protected ?int $reseededInBoot = null;

    protected ?int $reseededInBooted = null;

    protected ?int $reseededInHydrate = null;

    protected ?int $reseededLazily = null;

    protected ?int $rewrittenByAction = null;

    protected int $onlyReadDuringMount = 0;

    protected int $neverRead = 0;

    public int $publicValue = 0;

    protected static int $staticValue = 0;

    public function mount(object $service): void
    {
        $this->lost = 1;
        $this->lostPrivate = 'x';
        $this->lostService = $service;
        $this->readInRender = 1;
        $this->readByHelper = 1;
        $this->readThroughNestedWrite = new \stdClass;
        $this->reseededInBoot = 1;
        $this->reseededInBooted = 1;
        $this->reseededInHydrate = 1;
        $this->reseededLazily = 1;
        $this->rewrittenByAction = 1;
        $this->onlyReadDuringMount = 1;
        $this->neverRead = 1;
        $this->publicValue = 1;
        self::$staticValue = 1;
        $this->prepare();
    }

    private function prepare(): void
    {
        $this->setByMountHelper = $this->onlyReadDuringMount;
    }

    public function boot(): void
    {
        $this->reseededInBoot = 2;
    }

    public function booted(): void
    {
        $this->reseededInBooted = 2;
    }

    public function hydrate(): void
    {
        $this->reseededInHydrate = 2;
    }

    public function save(): int
    {
        $this->rewrittenByAction = 2;
        $this->readThroughNestedWrite->name = 'x';
        $this->reseededLazily ??= 2;

        return $this->lost
            + \strlen($this->lostPrivate)
            + $this->setByMountHelper
            + $this->publicValue
            + self::$staticValue
            + $this->rewrittenByAction
            + $this->reseededInBoot
            + $this->reseededInBooted
            + $this->reseededInHydrate
            + $this->reseededLazily
            + $this->helper()
            + $this->lostService->value;
    }

    private function helper(): int
    {
        return $this->readByHelper;
    }

    public function render(): mixed
    {
        return $this->readInRender;
    }
}

class NoMountComponent extends Component
{
    protected int $value = 0;

    public function read(): int
    {
        return $this->value;
    }
}

trait SeedsFromMountHook
{
    protected int $hookSeeded = 0;

    protected int $reseededByTraitHook = 0;

    protected int $reseededByInitialize = 0;

    public function mountSeedsFromMountHook(): void
    {
        $this->hookSeeded = 1;
        $this->reseededByTraitHook = 1;
        $this->reseededByInitialize = 1;
    }

    public function hydrateSeedsFromMountHook(): void
    {
        $this->reseededByTraitHook = 2;
    }

    public function initializeSeedsFromMountHook(): void
    {
        $this->reseededByInitialize = 2;
    }

    public function readThem(): int
    {
        return $this->hookSeeded + $this->reseededByTraitHook + $this->reseededByInitialize;
    }
}

class TraitSeededComponent extends Component
{
    use SeedsFromMountHook;
}

class DynamicWriteComponent extends Component
{
    protected int $value = 0;

    public function mount(): void
    {
        $this->value = 1;
    }

    public function set(string $name): void
    {
        $this->{$name} = 2;
    }

    public function read(): int
    {
        return $this->value;
    }
}

abstract class BaseWithProtectedState extends Component
{
    protected int $inherited = 0;

    public function hydrate(): void
    {
        $this->inherited = 2;
    }
}

class InheritsStateComponent extends BaseWithProtectedState
{
    public function mount(): void
    {
        $this->inherited = 1;
    }

    public function read(): int
    {
        return $this->inherited;
    }
}
