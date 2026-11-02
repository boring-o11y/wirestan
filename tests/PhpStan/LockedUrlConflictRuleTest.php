<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\LockedUrlConflictRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<LockedUrlConflictRule>
 */
final class LockedUrlConflictRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LockedUrlConflictRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_locked_url_properties_on_components_and_forms(): void
    {
        // $lockedOnly, $urlOnly and the plain class in the fixture must stay quiet.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/LockedUrlConflictComponent.php'],
            [
                [$this->message('LockedUrlConflictComponent', 'tenantId'), 14],
                [$this->message('LockedUrlConflictComponent', 'teamId'), 17],
                [$this->message('LockedUrlConflictForm', 'ownerId'), 30],
            ],
        );
    }

    private function message(string $class, string $property): string
    {
        return \sprintf(
            'Livewire public property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\%s::$%s is both #[Locked] and #[Url]. The lock only blocks updates: #[Url] still sets the value from the query string on page load, so the visitor chooses it. Remove #[Url], or remove #[Locked] and treat the value as user input.',
            $class,
            $property,
        );
    }
}
