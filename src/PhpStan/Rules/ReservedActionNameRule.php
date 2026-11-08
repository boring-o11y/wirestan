<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan\Rules;

use BoringO11y\Wirestan\PhpStan\ComponentMethods;
use BoringO11y\Wirestan\PhpStan\LivewireComponentReflectionDetector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassMethodsNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * `wire:click="upload"` runs `$wire.upload`, and the `$wire` proxy resolves its own
 * names before falling back to a server call, so an action with one of those names
 * can't be reached that way. Nothing reports this: the click does something else.
 *
 * @implements Rule<ClassMethodsNode>
 */
final class ReservedActionNameRule implements Rule
{
    /**
     * The `aliases` table in Livewire's js/$wire.js, plus `then` and `toJSON`, which the
     * proxy also answers itself. Names are case-sensitive in JavaScript.
     */
    private const RESERVED = [
        'call',
        'cancelUpload',
        'commit',
        'dirty',
        'dispatch',
        'dispatchEl',
        'dispatchRef',
        'dispatchSelf',
        'dispatchTo',
        'effect',
        'el',
        'entangle',
        'errors',
        'get',
        'hook',
        'id',
        'intercept',
        'interceptAction',
        'interceptMessage',
        'interceptRequest',
        'island',
        'js',
        'on',
        'refs',
        'removeUpload',
        'set',
        'then',
        'toJSON',
        'upload',
        'uploadMultiple',
        'watch',
    ];

    public function __construct(
        private readonly LivewireComponentReflectionDetector $detector,
    ) {}

    public function getNodeType(): string
    {
        return ClassMethodsNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (! $this->detector->isLivewireComponent($class)) {
            return [];
        }

        $methods = ComponentMethods::fromNode($node);
        $parent = $class->getParentClass();
        $errors = [];

        foreach ($methods->all() as $method) {
            $name = $method->name->toString();

            if (! \in_array($name, self::RESERVED, true) || ! ComponentMethods::isAction($method, $class)) {
                continue;
            }

            // Overriding something like Component::dispatch() isn't naming an action.
            if ($parent !== null && $parent->hasNativeMethod($name)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Livewire action %s::%s() can\'t be reached with wire:click="%s" or $wire.%s(), because $wire.%s is reserved by Livewire and runs instead. Rename the method.',
                $class->getDisplayName(),
                $name,
                $name,
                $name,
                $name,
            ))
                ->identifier('boringO11yWirestan.reservedActionName')
                ->line($methods->reportingLine($method))
                ->build();
        }

        return $errors;
    }
}
