<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\ResetTargetRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ResetTargetRule>
 */
final class ResetTargetRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ResetTargetRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_reset_targets_that_throw(): void
    {
        // Defaults, untyped properties, Forms, nested paths, a later reassignment, a
        // constructor-set property and non-literal arguments stay quiet.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/ResetTargetComponent.php'],
            [
                [$this->unknown('ResetTargetComponent', "reset('unknownFromTrait')", 'unknownFromTrait'), 16],
                [$this->unknown('ResetTargetComponent', "reset('nope')", 'nope'), 40],
                [$this->unknown('ResetTargetComponent', "reset('nope')", 'nope'), 45],
                [$this->unknown('ResetTargetComponent', "reset('nope.field')", 'nope'), 50],
                [$this->unknown('ResetTargetComponent', "reset('rows')", 'rows'), 55],
                [$this->nonPublic("reset('protectedValue')"), 60],
                [$this->nonPublic("reset('privateValue')"), 61],
                [$this->unset('ResetTargetComponent', "\$this->reset('typedNoDefault')", 'typedNoDefault'), 66],
                [$this->unset('ResetTargetComponent', "\$this->reset('nullableNoDefault')", 'nullableNoDefault'), 66],
                [$this->unknown('ResetTargetComponent', "pull('nope')", 'nope'), 71],
                [$this->unset('ResetTargetComponent', "\$this->pull('typedNoDefault')", 'typedNoDefault'), 72],
                [$this->unset('ResetEverythingComponent', '$this->reset()', 'post'), 118],
                [$this->unset('ResetEverythingComponent', '$this->pull()', 'post'), 123],
                [$this->unset('ResetEverythingComponent', '$this->resetExcept()', 'post'), 128],
            ],
        );
    }

    private function unknown(string $class, string $call, string $property): string
    {
        return \sprintf(
            'Livewire component BoringO11y\Wirestan\Tests\Fixtures\PhpStan\%s calls $this->%s, but has no property $%s, which throws at runtime.',
            $class,
            $call,
            $property,
        );
    }

    private function nonPublic(string $call): string
    {
        return \sprintf(
            'Livewire component BoringO11y\Wirestan\Tests\Fixtures\PhpStan\ResetTargetComponent calls $this->%s on a non-public property, which throws at runtime. Assign it directly instead.',
            $call,
        );
    }

    private function unset(string $class, string $call, string $property): string
    {
        return \sprintf(
            'Livewire component BoringO11y\Wirestan\Tests\Fixtures\PhpStan\%s calls %s, which unsets typed property $%s because it has no default value, so the next read throws. Give it a default value, leave it out of the reset, or reassign it in the same method.',
            $class,
            $call,
            $property,
        );
    }
}
