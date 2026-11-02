<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\LegacyComputedAccessorRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<LegacyComputedAccessorRule>
 */
final class LegacyComputedAccessorRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LegacyComputedAccessorRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_public_accessors_only(): void
    {
        // #[Computed], required-parameter, getProperty(), getProperties(), protected,
        // static and non-component methods in the fixture must stay quiet.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/LegacyComputedAccessorComponent.php'],
            [
                [$this->message('getFromTraitProperty'), 10],
                [$this->message('getTotalProperty'), 20],
                [$this->message('getOptionalArgumentProperty'), 25],
            ],
        );
    }

    private function message(string $method): string
    {
        return \sprintf(
            'Livewire accessor BoringO11y\Wirestan\Tests\Fixtures\PhpStan\LegacyComputedAccessorComponent::%s() can be called directly from the client, which then receives its return value. Convert it to a #[Computed] method, which Livewire refuses to call from the client.',
            $method,
        );
    }
}
