<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\LockedPublicPropertyRule;

/**
 * @extends RuleTestCase<LockedPublicPropertyRule>
 */
final class LockedPublicPropertyRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LockedPublicPropertyRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_property_seeded_in_mount_but_never_mutated(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/UnlockedImmutableComponent.php'],
            [
                [
                    'Livewire public property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\UnlockedImmutableComponent::$bookingId is never reassigned outside lifecycle methods. It must be marked #[Livewire\Attributes\Locked] — otherwise the client can mutate it via $wire.set.',
                    10,
                ],
            ],
        );
    }

    public function test_does_not_flag_property_with_locked_attribute(): void
    {
        // covered by the previous test — `$alreadyLocked` and `$name` are not in
        // the expected error list. `$name` is mutated by rename(), so it's
        // legitimately unlocked.
        $this->expectNotToPerformAssertions();
    }

    public function test_ignores_classes_that_are_not_livewire_components(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/NotALivewireComponent.php'],
            [],
        );
    }

    public function test_treats_list_destructuring_as_a_mutation(): void
    {
        // Both properties are reassigned via `[$this->a, $this->b] = …`, so the
        // rule must not demand #[Locked] on either.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/DestructuringMutationComponent.php'],
            [],
        );
    }

    public function test_skips_components_using_opaque_mass_assignment(): void
    {
        // $this->fill() can mutate any public property, so the rule can't prove
        // $bookingId is immutable and must not flag it.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/FillMutationComponent.php'],
            [],
        );
    }

    public function test_does_not_flag_livewire_form_property(): void
    {
        // A Livewire\Form property is mutated through nested wire:model fields,
        // never reassigned wholesale, so it reads as immutable — but locking it
        // breaks runtime. The rule must exempt Form subtypes.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/FormPropertyComponent.php'],
            [],
        );
    }

    public function test_reset_with_named_argument_only_exempts_that_property(): void
    {
        // $this->reset('search') resets only $search, so the component is NOT
        // wholesale-skipped — $bookingId (seeded, never mutated) is still flagged.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/ResetByNameComponent.php'],
            [
                [
                    'Livewire public property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\ResetByNameComponent::$bookingId is never reassigned outside lifecycle methods. It must be marked #[Livewire\Attributes\Locked] — otherwise the client can mutate it via $wire.set.',
                    9,
                ],
            ],
        );
    }
}
