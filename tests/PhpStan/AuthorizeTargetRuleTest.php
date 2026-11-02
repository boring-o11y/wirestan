<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\AuthorizeTargetRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<AuthorizeTargetRule>
 */
final class AuthorizeTargetRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new AuthorizeTargetRule(new LivewireComponentReflectionDetector, self::createReflectionProvider());
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_targets_that_resolve_to_nothing_or_to_raw_client_input(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/BrokenAuthorizeTargetComponent.php'],
            [
                [$this->unresolved('typo', 'psot'), 14],
                [$this->unresolved('nonPublicProperty', 'hidden'), 19],
                [$this->unresolved('notComputed', 'plainMethod'), 24],
                [$this->unresolved('unknownClass', 'App\\Models\\Missing'), 29],
                [$this->unresolved('arrayTarget', 'psot'), 34],
                [$this->unresolved('namedArgument', 'psot'), 39],
                [$this->unresolved('stacked', 'psot'), 45],
                ['Livewire action BoringO11y\Wirestan\Tests\Fixtures\PhpStan\BrokenAuthorizeTargetComponent::untypedParameter() has #[Authorize] targeting $comment, which has no type hint. Livewire needs the type to resolve the model, so the gate receives the raw client value.', 50],
            ],
        );
    }

    public function test_accepts_classes_parameters_properties_and_computed_targets(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/AllowedAuthorizeTargetComponent.php'],
            [],
        );
    }

    private function unresolved(string $method, string $target): string
    {
        return \sprintf(
            'Livewire action BoringO11y\Wirestan\Tests\Fixtures\PhpStan\BrokenAuthorizeTargetComponent::%s() has #[Authorize] targeting "%s", which is not a class, a parameter of the method, or a public or computed property of the component. The gate receives null instead of the object it should check.',
            $method,
            $target,
        );
    }
}
