<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\ReservedActionNameRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ReservedActionNameRule>
 */
final class ReservedActionNameRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ReservedActionNameRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_actions_named_like_wire_aliases(): void
    {
        // An override of a framework method, a different case, a longer name, computed,
        // static and non-public methods, and classes that aren't components stay quiet.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/ReservedActionNameComponent.php'],
            [
                [$this->message('hook'), 13],
                [$this->message('upload'), 17],
                [$this->message('get'), 19],
                [$this->message('on'), 21],
                [$this->message('then'), 23],
                [$this->message('toJSON'), 25],
            ],
        );
    }

    private function message(string $method): string
    {
        return \sprintf(
            'Livewire action BoringO11y\Wirestan\Tests\Fixtures\PhpStan\ReservedActionNameComponent::%1$s() can\'t be reached with wire:click="%1$s" or $wire.%1$s(), because $wire.%1$s is reserved by Livewire and runs instead. Rename the method.',
            $method,
        );
    }
}
