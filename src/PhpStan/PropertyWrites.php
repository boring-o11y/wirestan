<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Unset_;

/**
 * Everything one method writes through `$this`. Kept in one place so the rules agree
 * on what counts as a write.
 */
final class PropertyWrites
{
    /**
     * Built-in functions that change their first argument in place.
     */
    private const IN_PLACE_FUNCTIONS = [
        'array_multisort',
        'array_pop',
        'array_push',
        'array_shift',
        'array_splice',
        'array_unshift',
        'array_walk',
        'array_walk_recursive',
        'arsort',
        'asort',
        'krsort',
        'ksort',
        'natcasesort',
        'natsort',
        'rsort',
        'shuffle',
        'sort',
        'uasort',
        'uksort',
        'usort',
    ];

    /**
     * @var list<PropertyWrite>
     */
    public private(set) array $named = [];

    /**
     * @var list<MassWrite>
     */
    public private(set) array $mass = [];

    /**
     * Whether it writes `$this->{$name}`, where the name can't be known.
     */
    public private(set) bool $dynamic = false;

    private function __construct() {}

    public static function in(ClassMethod $method): self
    {
        $writes = new self;

        foreach (ComponentMethods::findIn($method, static fn (Node $node): bool => true) as $node) {
            $writes->inspect($node);
        }

        return $writes;
    }

    /**
     * The literal names in `reset('a', 'b')`, `reset(['a', 'b'])` or `reset()`, which
     * gives an empty list. Null when any argument isn't a literal.
     *
     * @return list<string>|null
     */
    public static function literalNames(MethodCall $call): ?array
    {
        if ($call->isFirstClassCallable()) {
            return null;
        }

        $names = [];

        foreach ($call->getArgs() as $arg) {
            if ($arg->unpack) {
                return null;
            }

            if ($arg->value instanceof String_) {
                $names[] = $arg->value->value;

                continue;
            }

            if (! $arg->value instanceof Array_) {
                return null;
            }

            foreach ($arg->value->items as $item) {
                if (! $item->value instanceof String_) {
                    return null;
                }

                $names[] = $item->value->value;
            }
        }

        return $names;
    }

    /**
     * `items.0.name` resets or validates part of the `items` property.
     */
    public static function rootOf(string $path): string
    {
        return explode('.', $path, 2)[0];
    }

    private function inspect(Node $node): void
    {
        if (
            $node instanceof Assign
            || $node instanceof AssignOp
            || $node instanceof PreInc
            || $node instanceof PreDec
            || $node instanceof PostInc
            || $node instanceof PostDec
        ) {
            $this->addTarget($node->var);
        } elseif ($node instanceof AssignRef) {
            $this->addTarget($node->var);
            $this->addTarget($node->expr);
        } elseif ($node instanceof Unset_) {
            foreach ($node->vars as $var) {
                $this->addTarget($var);
            }
        } elseif ($node instanceof Foreach_) {
            $this->addLoop($node);
        } elseif ($node instanceof FuncCall) {
            $this->addInPlaceCall($node);
        } elseif ($node instanceof MethodCall) {
            $this->addMassCall($node);
        }
    }

    private function addLoop(Foreach_ $loop): void
    {
        $this->addTarget($loop->valueVar);

        if ($loop->keyVar !== null) {
            $this->addTarget($loop->keyVar);
        }

        if ($loop->byRef) {
            $this->addTarget($loop->expr);
        }
    }

    private function addInPlaceCall(FuncCall $call): void
    {
        if (! $call->name instanceof Name || $call->isFirstClassCallable()) {
            return;
        }

        if (! \in_array($call->name->toLowerString(), self::IN_PLACE_FUNCTIONS, true)) {
            return;
        }

        $first = $call->getArgs()[0] ?? null;

        if ($first instanceof Arg) {
            $this->addTarget($first->value);
        }
    }

    private function addTarget(Node $target): void
    {
        if ($target instanceof List_ || $target instanceof Array_) {
            foreach ($target->items as $item) {
                // A skipped slot, as in `[, $b] = $pair`, is null.
                if ($item !== null) {
                    $this->addTarget($item->value);
                }
            }

            return;
        }

        $nested = false;

        while (true) {
            if ($target instanceof ArrayDimFetch) {
                $target = $target->var;

                continue;
            }

            if ($target instanceof PropertyFetch && ! ($target->var instanceof Variable && $target->var->name === 'this')) {
                $target = $target->var;
                $nested = true;

                continue;
            }

            break;
        }

        if (! $target instanceof PropertyFetch) {
            return;
        }

        if (! $target->name instanceof Identifier) {
            $this->dynamic = true;

            return;
        }

        $this->named[] = new PropertyWrite($target->name->toString(), $target->getStartLine(), $nested);
    }

    private function addMassCall(MethodCall $call): void
    {
        $method = ComponentMethods::thisMember($call);
        if ($method === null) {
            return;
        }

        $line = $call->getStartLine();
        $label = '$this->' . $method . '()';

        switch (strtolower($method)) {
            case 'reset':
            case 'pull':
                $this->addReset($call, $label, $line);
                break;
            case 'resetexcept':
                $names = self::literalNames($call);
                $this->mass[] = new MassWrite($label, $line, except: $names === null ? null : array_map(self::rootOf(...), $names));
                break;
            case 'fill':
                $this->mass[] = new MassWrite($label, $line, names: $this->literalKeys($call));
                break;
            case 'filldata':
            case 'mergedata':
                $this->mass[] = new MassWrite($label, $line);
                break;
        }
    }

    private function addReset(MethodCall $call, string $label, int $line): void
    {
        $names = self::literalNames($call);

        if ($names === null) {
            $this->mass[] = new MassWrite($label, $line);

            return;
        }

        if ($names === []) {
            $this->mass[] = new MassWrite($label, $line, except: []);

            return;
        }

        foreach ($names as $name) {
            $this->named[] = new PropertyWrite(self::rootOf($name), $line, viaCall: true);
        }
    }

    /**
     * @return list<string>
     */
    private function literalKeys(MethodCall $call): array
    {
        $values = $call->isFirstClassCallable() ? null : ($call->getArgs()[0]->value ?? null);

        if (! $values instanceof Array_) {
            return [];
        }

        $keys = [];

        foreach ($values->items as $item) {
            if (! $item->key instanceof String_) {
                return [];
            }

            $keys[] = self::rootOf($item->key->value);
        }

        return $keys;
    }
}
