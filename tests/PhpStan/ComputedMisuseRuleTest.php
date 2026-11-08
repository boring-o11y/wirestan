<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\Tests\PhpStan;

use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use BoringO11y\Wirestan\PhpStan\Rules\ComputedMisuseRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ComputedMisuseRule>
 */
final class ComputedMisuseRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ComputedMisuseRule(new LivewireComponentReflectionDetector);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan-test.neon'];
    }

    public function test_flags_misused_computed_methods(): void
    {
        // Optional and variadic parameters, a static same-named property, calls with an
        // argument, first-class callables, property reads and plain methods stay quiet.
        $this->analyse(
            [__DIR__ . '/../Fixtures/PhpStan/ComputedMisuseComponent.php'],
            [
                [$this->requiredParameter('fromTrait', 'required'), 23],
                [$this->directCall('withDefault'), 23],
                [$this->requiredParameter('required', 'id'), 38],
                [$this->requiredParameter('required', 'service'), 38],
                [$this->shadowed('shadowedPublic'), 50],
                [$this->shadowed('shadowedProtected'), 56],
                [$this->shadowed('shadowedPrivate'), 62],
                [$this->directCall('withDefault'), 75],
                [
                    'Computed properties aren\'t supported on Livewire Form objects: BoringO11y\Wirestan\Tests\Fixtures\PhpStan\ComputedOnFormObject::upper() has #[Computed], but reading $form->upper throws "Undefined property". Move it to the component.',
                    107,
                ],
            ],
        );
    }

    private function requiredParameter(string $method, string $parameter): string
    {
        return \sprintf(
            'Livewire computed property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\ComputedMisuseComponent::%s() has required parameter $%s, which throws ArgumentCountError: Livewire calls computed methods with no arguments and doesn\'t inject dependencies. Give it a default value or remove it.',
            $method,
            $parameter,
        );
    }

    private function shadowed(string $method): string
    {
        return \sprintf(
            'Livewire computed property BoringO11y\Wirestan\Tests\Fixtures\PhpStan\ComputedMisuseComponent::%1$s() never runs when read as $this->%1$s, because a property with the same name answers first. Rename one of them.',
            $method,
        );
    }

    private function directCall(string $method): string
    {
        return \sprintf(
            'Calling BoringO11y\Wirestan\Tests\Fixtures\PhpStan\ComputedMisuseComponent::%1$s() directly runs it again and skips #[Computed]. Read $this->%1$s instead, which is memoized for the request.',
            $method,
        );
    }
}
