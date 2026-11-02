<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * Decides whether an action checks authorization, in its own body, in a `$this->`
 * helper it calls, or in a hook that runs on every request. `mount()` doesn't count:
 * it only runs on the first request.
 *
 * A method's own #[Authorize] attribute is the caller's concern, because Livewire
 * only honours it when the client calls that method directly, never on an internal
 * `$this->helper()` call.
 */
final class AuthorizationGuardDetector
{
    private const EVERY_REQUEST_HOOKS = ['boot', 'booted', 'hydrate'];

    private const GUARD_FUNCTIONS = ['abort', 'abort_if', 'abort_unless'];

    private const GUARD_METHODS = ['authorize', 'authorizeforuser', 'can', 'cannot', 'cant'];

    private const IDENTITY_METHODS = ['is', 'isnot'];

    private const GATE_FACADES = ['Gate', 'Illuminate\\Support\\Facades\\Gate'];

    private const AUTHORIZATION_EXCEPTION = 'Illuminate\\Auth\\Access\\AuthorizationException';

    /**
     * @var list<string>
     */
    private readonly array $customGuards;

    /**
     * @param  list<string>  $authorizationMethods  extra method or function names that count as a guard
     */
    public function __construct(array $authorizationMethods = [])
    {
        $this->customGuards = array_map(strtolower(...), $authorizationMethods);
    }

    public function covers(ClassMethod $action, ComponentMethods $methods): bool
    {
        $entries = [$action];

        foreach (self::EVERY_REQUEST_HOOKS as $hook) {
            $method = $methods->find($hook);
            if ($method !== null) {
                $entries[] = $method;
            }
        }

        foreach ($entries as $entry) {
            foreach ($methods->reachableFrom($entry) as $method) {
                if (ComponentMethods::findIn($method, $this->isGuard(...)) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isGuard(Node $node): bool
    {
        if ($node instanceof FuncCall) {
            if (! $node->name instanceof Name) {
                return false;
            }
            $name = $node->name->toLowerString();

            return \in_array($name, self::GUARD_FUNCTIONS, true) || \in_array($name, $this->customGuards, true);
        }

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            if (! $node->name instanceof Identifier) {
                return false;
            }
            $name = $node->name->toLowerString();

            return \in_array($name, self::GUARD_METHODS, true)
                || \in_array($name, $this->customGuards, true)
                || (\in_array($name, self::IDENTITY_METHODS, true) && $this->readsAuth([$node->var, ...$node->args]));
        }

        if ($node instanceof StaticCall) {
            if ($node->class instanceof Name && \in_array($node->class->toString(), self::GATE_FACADES, true)) {
                return true;
            }

            return $node->name instanceof Identifier
                && \in_array($node->name->toLowerString(), $this->customGuards, true);
        }

        if ($node instanceof New_) {
            return $node->class instanceof Name && $node->class->toString() === self::AUTHORIZATION_EXCEPTION;
        }

        // Ownership checks: `$post->user_id !== auth()->id()`.
        if ($node instanceof Identical || $node instanceof NotIdentical || $node instanceof Equal || $node instanceof NotEqual) {
            return $this->readsAuth([$node->left, $node->right]);
        }

        return false;
    }

    /**
     * @param  array<Node>  $nodes
     */
    private function readsAuth(array $nodes): bool
    {
        return (new NodeFinder)->findFirst($nodes, RequestContext::readsAuth(...)) !== null;
    }
}
