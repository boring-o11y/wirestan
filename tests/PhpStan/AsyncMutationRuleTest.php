<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\AsyncMutationRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<AsyncMutationRule>
 */
final class AsyncMutationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new AsyncMutationRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_async_actions_that_write_public_state(): void
    {
        // Reads, non-public and static writes, model attributes, unset() on a computed
        // and non-async actions must stay quiet.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/AsyncMutationComponent.php'],
            [
                [$this->message('increment', '$this->count'), 24],
                [$this->message('throughHelper', '$this->items'), 30],
                [$this->message('writesTwo', '$this->count, $this->items'), 41],
                [$this->message('resetsEverything', '$this->reset()'), 49],
                [$this->message('resetsByName', '$this->count'), 55],
                [$this->message('fills', '$this->fill()'), 61],
                [$this->message('writesFormField', '$this->form'), 67],
                [$this->message('replacesModel', '$this->post'), 73],
            ],
        );
    }

    private function message(string $method, string $written): string
    {
        return \sprintf(
            'Livewire #[Async] action BoringO11y\Wirestan\Tests\Fixtures\PhpStan\AsyncMutationComponent::%s() writes %s. Async actions run in parallel from the same starting state, so concurrent calls overwrite each other. Remove #[Async], or don\'t change public properties in this action.',
            $method,
            $written,
        );
    }
}
