<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\AuthorizationGuardDetector;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\ModelParamWithoutAuthorizationRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ModelParamWithoutAuthorizationRule>
 */
final class ModelParamWithoutAuthorizationRuleTest extends RuleTestCase
{
    /**
     * @var list<string>
     */
    private array $authorizationMethods = [];

    protected function getRule(): Rule
    {
        return new ModelParamWithoutAuthorizationRule(
            new LivewireComponentReflectionDetector,
            new AuthorizationGuardDetector($this->authorizationMethods),
        );
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_client_reachable_methods_binding_a_model_without_a_guard(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/UnguardedModelParamComponent.php'],
            [
                // Declared in a trait, so reported on the class that uses it.
                [$this->message('UnguardedModelParamComponent', 'deleteFromTrait', '$post'), 16],
                [$this->message('UnguardedModelParamComponent', 'delete', '$post'), 20],
                [$this->message('UnguardedModelParamComponent', 'nullable', '$post'), 24],
                [$this->message('UnguardedModelParamComponent', 'twoModels', '$post, $other'), 28],
                [$this->message('UnguardedModelParamComponent', 'handleRemoved', '$post'), 33],
                [$this->message('UnguardedModelParamComponent', 'updatedFromEvent', '$post'), 39],
                [$this->message('UnguardedModelParamComponent', 'viaAttributeOnHelper', '$post'), 44],
                [$this->message('UnguardedModelParamComponent', 'identityCheckWithoutAuth', '$post'), 54],
                [$this->message('MountOnlyGuardedComponent', 'delete', '$post'), 70],
            ],
        );
    }

    public function test_accepts_every_guard_form_and_unreachable_methods(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/GuardedModelParamComponent.php'],
            [],
        );
    }

    public function test_guard_defined_elsewhere_is_flagged_until_configured(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/CustomGuardComponent.php'],
            [
                [$this->message('CustomGuardComponent', 'delete', '$post'), 9],
            ],
        );
    }

    public function test_configured_guard_counts_as_authorization(): void
    {
        $this->authorizationMethods = ['ensureOwnership'];

        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/CustomGuardComponent.php'],
            [],
        );
    }

    private function message(string $class, string $method, string $parameters): string
    {
        return \sprintf(
            'Livewire action BoringO11y\Wirestan\Tests\Fixtures\PhpStan\%s::%s() resolves %s from an id the client supplies, so a browser can name any record, but it has no authorization check. Add #[Authorize], call $this->authorize(), or list your own guard under wirestan.authorizationMethods.',
            $class,
            $method,
            $parameters,
        );
    }
}
