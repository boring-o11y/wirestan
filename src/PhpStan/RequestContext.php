<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;

/**
 * Recognises reads of per-request state: who the user is and what they sent.
 */
final class RequestContext
{
    private const AUTH_READS = ['auth()', 'Auth::'];

    private const FUNCTIONS = ['auth', 'request', 'session', 'cookie'];

    private const FACADES = ['Auth', 'Request', 'Session', 'Cookie', 'Gate'];

    /**
     * A label for the read (`auth()`, `Auth::`), or null if the node isn't one.
     */
    public static function read(Node $node): ?string
    {
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $name = $node->name->toLowerString();

            return \in_array($name, self::FUNCTIONS, true) ? $name . '()' : null;
        }

        if ($node instanceof StaticCall && $node->class instanceof Name) {
            $class = $node->class->toString();

            foreach (self::FACADES as $facade) {
                if ($class === $facade || $class === 'Illuminate\\Support\\Facades\\' . $facade) {
                    return $facade . '::';
                }
            }
        }

        return null;
    }

    public static function readsAuth(Node $node): bool
    {
        return \in_array(self::read($node), self::AUTH_READS, true);
    }
}
