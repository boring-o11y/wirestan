<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\ValidationNeverEnforcedRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ValidationNeverEnforcedRule>
 */
final class ValidationNeverEnforcedRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ValidationNeverEnforcedRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_components_with_rules_and_actions_but_no_validation_call(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/UnenforcedValidationComponent.php'],
            [
                [$this->message('ValidateAttributeWithoutCallComponent'), 9],
                [$this->message('RuleAttributeWithoutCallComponent'), 19],
                [$this->message('RulesMethodWithoutCallComponent'), 29],
                [$this->message('RulesPropertyWithoutCallComponent'), 43],
                [$this->message('TraitRulesWithoutCallComponent'), 58],
            ],
        );
    }

    public function test_stays_quiet_when_enforced_or_when_absence_cannot_be_proven(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/EnforcedValidationComponent.php'],
            [],
        );
    }

    private function message(string $class): string
    {
        return \sprintf(
            'Livewire component BoringO11y\Wirestan\Tests\Fixtures\PhpStan\%s declares validation rules but never calls validate() or validateOnly(). Rules alone do not stop an action from running: call $this->validate() before using the input.',
            $class,
        );
    }
}
