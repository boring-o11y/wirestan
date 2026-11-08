<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\NonPersistedStateRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NonPersistedStateRule>
 */
final class NonPersistedStateRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NonPersistedStateRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_state_set_in_mount_and_read_later(): void
    {
        // Anything re-seeded on later requests, read only during mount or never, public,
        // static, or inherited stays quiet, as does a component with a dynamic write.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/NonPersistedStateComponent.php'],
            [
                [$this->message('NonPersistedStateComponent', 'lost', 'mount', 'save'), 43],
                [$this->message('NonPersistedStateComponent', 'lostPrivate', 'mount', 'save'), 44],
                [$this->message('NonPersistedStateComponent', 'lostService', 'mount', 'save'), 45],
                [$this->message('NonPersistedStateComponent', 'readInRender', 'mount', 'render'), 46],
                [$this->message('NonPersistedStateComponent', 'readByHelper', 'mount', 'helper'), 47],
                [$this->message('NonPersistedStateComponent', 'readThroughNestedWrite', 'mount', 'save'), 48],
                [$this->message('NonPersistedStateComponent', 'setByMountHelper', 'prepare', 'save'), 63],
                [$this->message('TraitSeededComponent', 'hookSeeded', 'mountSeedsFromMountHook', 'readThem'), 153],
            ],
        );
    }

    private function message(string $class, string $property, string $seed, string $read): string
    {
        return \sprintf(
            'Livewire component BoringO11y\Wirestan\Tests\Fixtures\PhpStan\%s sets non-public property $%s in %s() and reads it in %s(), but only public properties survive between requests, so the value is lost after the first one. Set it in boot() or hydrate(), which run on every request, or make it public with #[Locked] if it holds plain data.',
            $class,
            $property,
            $seed,
            $read,
        );
    }
}
