<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\ComputedCacheScopeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ComputedCacheScopeRule>
 */
final class ComputedCacheScopeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ComputedCacheScopeRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_shared_caches_that_depend_on_the_user_or_state_and_stale_persisted_values(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/MisscopedComputedCacheComponent.php'],
            [
                [$this->shared('viaAuthHelper', 'auth()'), 19],
                [$this->shared('viaFacade', 'Auth::'), 25],
                [$this->shared('viaRequest', 'request()'), 31],
                [$this->shared('viaSession', 'session()'), 37],
                [$this->shared('viaGate', 'Gate::'), 43],
                [$this->shared('viaProperty', '$this->search'), 49],
                [$this->shared('viaLockedProperty', '$this->tenantId'), 55],
                [$this->shared('viaHelperMethod', 'auth()'), 61],
                [$this->shared('viaOtherComputed', 'auth()'), 67],
                [$this->shared('viaNamedKey', 'auth()'), 73],
                [$this->shared('persistWithKey', 'auth()'), 79],
                [$this->shared('persistWithPositionalKey', 'auth()'), 85],
                [$this->shared('multipleReads', 'auth(), $this->search'), 91],
                [$this->shared('viaPlaceholderKey', '$this->search'), 98],
                [$this->stale('results'), 114],
                [$this->stale('viaHelper'), 120],
            ],
        );
    }

    public function test_accepts_correctly_scoped_caches(): void
    {
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/AllowedComputedCacheComponent.php'],
            [],
        );
    }

    private function shared(string $method, string $reads): string
    {
        return \sprintf(
            'Livewire computed property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\SharedComputedCacheComponent::%s() is cached for all users but depends on %s, so every visitor receives the first caller\'s value. Use #[Computed(persist: true)] without a key, or remove the caching.',
            $method,
            $reads,
        );
    }

    private function stale(string $method): string
    {
        return \sprintf(
            'Livewire computed property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\StalePersistedComputedComponent::%s() is cached per component (persist: true) but depends on $this->search, which can change without the cached value updating. Bust it with unset($this->%s), mark the property #[Locked], or remove persist.',
            $method,
            $method,
        );
    }
}
