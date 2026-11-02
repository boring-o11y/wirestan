<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\PublicReservedPropertyRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<PublicReservedPropertyRule>
 */
final class PublicReservedPropertyRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new PublicReservedPropertyRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_every_public_reserved_property(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/PublicReservedPropertyComponent.php'],
            [
                [$this->message('rules'), 9],
                [$this->message('messages'), 11],
                [$this->message('validationAttributes'), 13],
                [$this->message('validationCustomValues'), 15],
                [$this->message('listeners'), 17],
                [$this->message('queryString'), 19],
                [$this->message('paginationTheme'), 21],
            ],
        );
    }

    public function test_ignores_protected_locked_static_and_non_component_properties(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/ProtectedReservedPropertyComponent.php'],
            [],
        );
    }

    private function message(string $property): string
    {
        return \sprintf(
            'Livewire property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\PublicReservedPropertyComponent::$%s configures the framework but is public, so the client can overwrite it via $wire.set. Make it protected.',
            $property,
        );
    }
}
