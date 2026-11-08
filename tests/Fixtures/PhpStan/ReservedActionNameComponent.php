<?php

namespace BoringO11y\Wirestan\Tests\Fixtures\PhpStan;

use Livewire\Attributes\Computed;
use Livewire\Component;

trait ReservedNameFromTrait
{
    public function hook(): void {}
}

class ReservedActionNameComponent extends Component
{
    use ReservedNameFromTrait;

    public function upload(): void {}

    public function get(): void {}

    public function on(): void {}

    public function then(): void {}

    public function toJSON(): void {}

    // Overrides the framework's own method rather than naming an action.
    public function dispatch(string $event, mixed ...$params): mixed
    {
        return null;
    }

    // JavaScript names are case-sensitive, so $wire.Set falls through to the server.
    public function Set(): void {}

    public function uploadPhoto(): void {}

    #[Computed]
    public function errors(): array
    {
        return [];
    }

    public static function watch(): void {}

    protected function commit(): void {}

    private function entangle(): void {}
}

class NotAComponentWithReservedNames
{
    public function upload(): void {}
}
