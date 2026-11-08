<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use BoringO11y\Wirestan\PhpStan\ComponentViews;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\LockedPublicPropertyRule;

/**
 * @extends RuleTestCase<LockedPublicPropertyRule>
 */
final class LockedPublicPropertyRuleTest extends RuleTestCase
{
    private const TIP = 'wirestan could not read every view this component renders, so a binding may be missing here. If a wire:model does bind this property, ignore this error rather than locking it: a locked input throws CannotUpdateLockedPropertyException.';

    protected function getRule(): Rule
    {
        return new LockedPublicPropertyRule(
            new LivewireComponentReflectionDetector,
            new ComponentViews(
                [__DIR__ . '/../Fixtures/views'],
                ['Shop' => [__DIR__ . '/../Fixtures/modules/Missing/Views', __DIR__ . '/../Fixtures/modules/Shop/Views']],
            ),
        );
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
                    $this->message('UnlockedImmutableComponent'),
                    10,
                    self::TIP,
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

    public function test_does_not_flag_url_bound_property(): void
    {
        // #[Url] means the visitor sets the value, and #[Locked] alongside it is
        // what LockedUrlConflictRule reports, so asking for the lock would leave
        // no way to satisfy both rules.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/UrlBoundComponent.php'],
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
                    $this->message('ResetByNameComponent'),
                    9,
                    self::TIP,
                ],
            ],
        );
    }

    public function test_does_not_flag_attributes_that_mark_client_input(): void
    {
        $this->assertOnlyBookingIdFlagged('LockedAttributeInputsComponent', 34);
    }

    public function test_does_not_flag_property_with_an_update_hook(): void
    {
        // Livewire only calls updatedTitle() when the client sets $title.
        $this->assertOnlyBookingIdFlagged('LockedUpdateHooksComponent', 15);
    }

    public function test_does_not_flag_models_and_eloquent_collections(): void
    {
        $this->assertOnlyBookingIdFlagged('LockedModelStateComponent', 23);
    }

    public function test_does_not_flag_property_with_validation_rules(): void
    {
        $this->assertOnlyBookingIdFlagged('LockedValidationTargetsComponent', 19);
    }

    public function test_does_not_flag_property_in_the_query_string(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/LockedQueryStringComponent.php'],
            [
                [$this->message('LockedQueryStringComponent'), 15, self::TIP],
                [$this->message('LockedQueryStringMethodComponent'), 24, self::TIP],
            ],
        );
    }

    public function test_treats_in_place_changes_as_mutations(): void
    {
        $this->assertOnlyBookingIdFlagged('LockedInPlaceWritesComponent', 29);
    }

    public function test_sees_writes_hooks_and_rules_in_traits(): void
    {
        $this->assertOnlyBookingIdFlagged('LockedTraitWritesComponent', 32);
    }

    private function assertOnlyBookingIdFlagged(string $fixture, int $line): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/' . $fixture . '.php'],
            [[$this->message($fixture), $line, self::TIP]],
        );
    }

    public function test_reads_bindings_from_the_conventional_view(): void
    {
        // Commented-out bindings, a `$parent.` binding and a value that's only displayed
        // don't make a property client input.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/LockedBoundByConventionComponent.php'],
            [
                [$this->message('LockedBoundByConventionComponent', 'commentedOut'), 40],
                [$this->message('LockedBoundByConventionComponent', 'parentsProperty'), 42],
                [$this->message('LockedBoundByConventionComponent', 'onlyDisplayed'), 44],
                [$this->message('LockedBoundByConventionComponent'), 46],
            ],
        );
    }

    public function test_reads_bindings_from_the_rendered_view_and_its_includes(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/LockedRenderedViewComponent.php'],
            [[$this->message('LockedRenderedViewComponent'), 23]],
        );
    }

    public function test_reads_bindings_from_views_in_a_configured_namespace(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/LockedNamespacedModuleViewComponent.php'],
            [[$this->message('LockedNamespacedModuleViewComponent'), 13]],
        );
    }

    public function test_reads_bindings_from_inline_templates_and_the_view_facade(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/LockedInlineViewComponent.php'],
            [
                [$this->message('LockedInlineViewComponent'), 12],
                [$this->message('LockedFacadeViewComponent', 'inView'), 24],
                [$this->message('LockedFacadeViewComponent'), 26],
                [$this->message('LockedViewHelperMakeComponent'), 38],
            ],
        );
    }

    public function test_stays_quiet_when_a_view_computes_what_it_binds(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/LockedDynamicBindingComponent.php'],
            [],
        );
    }

    public function test_warns_when_it_cannot_read_every_view(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/LockedUnresolvedViewComponent.php'],
            [
                [$this->message('LockedMissingViewComponent', 'title'), 9, self::TIP],
                [$this->message('LockedNamespacedViewComponent', 'title'), 19, self::TIP],
                [$this->message('LockedComputedViewNameComponent', 'title'), 29, self::TIP],
                [$this->message('LockedPartlyResolvedViewComponent', 'title'), 46, self::TIP],
            ],
        );
    }

    public function test_reads_the_view_of_a_single_file_component(): void
    {
        $file = realpath(__DIR__ . '/../Fixtures/views/components/⚡locked-sfc.blade.php');

        $this->analyse(
            [$file],
            [[$this->anonymousMessage($file, 5), 9]],
        );
    }

    public function test_reads_the_view_of_a_multi_file_component(): void
    {
        $file = realpath(__DIR__ . '/../Fixtures/views/components/⚡locked-mfc/locked-mfc.php');

        $this->analyse(
            [$file],
            [[$this->anonymousMessage($file, 5), 9]],
        );
    }

    private function anonymousMessage(string|false $file, int $line): string
    {
        return \sprintf(
            'Livewire public property Livewire\Component@anonymous/%s:%d::$bookingId is never reassigned outside lifecycle methods. It must be marked #[Livewire\Attributes\Locked] — otherwise the client can mutate it via $wire.set.',
            $file,
            $line,
        );
    }

    private function message(string $class, string $property = 'bookingId'): string
    {
        return \sprintf(
            'Livewire public property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\%s::$%s is never reassigned outside lifecycle methods. It must be marked #[Livewire\Attributes\Locked] — otherwise the client can mutate it via $wire.set.',
            $class,
            $property,
        );
    }
}
