<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\ReactiveMutationRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ReactiveMutationRule>
 */
final class ReactiveMutationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ReactiveMutationRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_every_write_that_throws(): void
    {
        // __construct and dehydrate writes, resetExcept() naming all of them, fill()
        // with other keys or unreadable ones, and reads must stay quiet.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/ReactiveMutationComponent.php'],
            [
                [$this->message('count', 'fromTrait'), 16],
                [$this->message('count', 'mount'), 39],
                [$this->message('count', 'boot'), 44],
                [$this->message('count', 'hydrate'), 49],
                [$this->message('count', 'render'), 54],
                [$this->message('count', 'increment'), 61],
                [$this->message('items', 'appendItem'), 66],
                [$this->message('items', 'removeItem'), 71],
                [$this->message('items', 'sortItems'), 76],
                [$this->message('post', 'renamePost'), 81],
                [$this->message('count', 'resetCount'), 86],
                [$this->message('count', 'fillCount', '$this->fill()'), 91],
                [$this->message('count', 'resetEverything', '$this->reset()'), 96],
                [$this->message('items', 'resetEverything', '$this->reset()'), 96],
                [$this->message('post', 'resetEverything', '$this->reset()'), 96],
                [$this->message('count', 'resetAllButOwn', '$this->resetExcept()'), 101],
                [$this->message('items', 'resetAllButOwn', '$this->resetExcept()'), 101],
                [$this->message('post', 'resetAllButOwn', '$this->resetExcept()'), 101],
            ],
        );
    }

    private function message(string $property, string $method, ?string $call = null): string
    {
        return \sprintf(
            'Livewire #[Reactive] property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\ReactiveMutationComponent::$%s is written%s in %s(), which throws CannotMutateReactivePropException. The value belongs to the parent component: change it there, or copy it to another property.',
            $property,
            $call === null ? '' : ' by ' . $call,
            $method,
        );
    }
}
