<?php

declare(strict_types=1);

namespace BoringO11y\Wirestan\PhpStan;

/**
 * The properties a component's Blade views let the client write.
 */
final class ViewBindings
{
    private const WIRE_MODEL = '/(?<![\w-])wire:model(?:\.[\w-]+)*\s*=\s*(["\'])(.*?)\1/s';

    /**
     * `$set('x', 1)`, `$toggle('x')`, `$wire.set('x', 1)`, `$wire.$entangle('x')`,
     * `@entangle('x')` and `$wire.upload('x', $file)`.
     */
    private const WRITE_CALL = '/(?:\$wire\.\$?|@this\.\$?|\$)(?:set|toggle|entangle|upload|uploadMultiple)\(\s*(?:(["\'])\s*([A-Za-z_]\w*)|([^\s"\']))|@entangle\(\s*(["\'])\s*([A-Za-z_]\w*)/';

    /**
     * `$wire.x = 1`, `$wire.x.y = 1`, `$wire.x++` and `$wire.x += 1`.
     */
    private const WIRE_ASSIGNMENT = '/\$wire\.([A-Za-z_]\w*)(?:\.\w+|\[[^\]]*\])*\s*(?:=(?!=)|\+\+|--|[-+*\/]=)/';

    private const ALPINE_MODEL = '/(?<![\w-])x-model(?:\.[\w-]+)*\s*=\s*(["\'])\s*\$wire\.([A-Za-z_]\w*)/';

    /**
     * Livewire 4 reads bare names as `$wire.` ones, so `wire:click="count++"` writes.
     */
    private const WIRE_EVENT_ASSIGNMENT = '/(?<![\w-])wire:[\w.-]+\s*=\s*(["\'])\s*([A-Za-z_]\w*)(?:\.\w+)*\s*(?:=(?!=)|\+\+|--|[-+*\/]=)/';

    private const COMMENTS = '/\{\{--.*?--\}\}|<!--.*?-->/s';

    private const INCLUDE = '/@(?:include|each)\(\s*(?:(["\'])([^"\']+)\1|[^\s"\'])/';

    private const INCLUDE_IF = '/@includeIf\(\s*(?:(["\'])([^"\']+)\1|[^\s"\'])/';

    private const INCLUDE_WHEN = '/@include(?:When|Unless)\([^,]+,\s*(?:(["\'])([^"\']+)\1|[^\s"\'])/';

    private const INCLUDE_FIRST = '/@includeFirst\(\s*\[(.*?)\]/s';

    /**
     * @param  array<string, true>  $names
     * @param  list<string>  $includes
     * @param  array<string, true>  $optionalIncludes  Blade skips these if they don't exist.
     */
    private function __construct(
        private readonly array $names = [],
        private readonly bool $dynamic = false,
        public readonly array $includes = [],
        public readonly bool $complete = true,
        public readonly array $optionalIncludes = [],
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public static function fromBlade(string $blade): self
    {
        $blade = (string) preg_replace(self::COMMENTS, '', $blade);

        $names = [];
        $dynamic = false;

        preg_match_all(self::WIRE_MODEL, $blade, $matches);
        foreach ($matches[2] as $expression) {
            // `$parent.x` binds a property of the parent component, not this one.
            if (str_starts_with(ltrim($expression), '$parent')) {
                continue;
            }

            if (preg_match('/^\s*([A-Za-z_]\w*)/', $expression, $root) === 1) {
                $names[$root[1]] = true;
            } else {
                $dynamic = true;
            }
        }

        preg_match_all(self::WRITE_CALL, $blade, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        foreach ($matches as $match) {
            $literal = $match[2] ?? $match[5] ?? null;

            if ($literal !== null) {
                $names[$literal] = true;
            } elseif (($match[3] ?? null) !== null) {
                $dynamic = true;
            }
        }

        // The property name is the last group captured by each of these.
        foreach ([self::WIRE_ASSIGNMENT, self::ALPINE_MODEL, self::WIRE_EVENT_ASSIGNMENT] as $pattern) {
            preg_match_all($pattern, $blade, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $names[(string) end($match)] = true;
            }
        }

        [$required, $complete] = self::literalViews($blade, self::INCLUDE, self::INCLUDE_WHEN);
        [$optional, $optionalComplete] = self::literalViews($blade, self::INCLUDE_IF);

        preg_match_all(self::INCLUDE_FIRST, $blade, $matches);
        foreach ($matches[1] as $list) {
            preg_match_all('/["\']([^"\']+)["\']/', $list, $views);
            array_push($optional, ...$views[1]);
        }

        return new self(
            $names,
            $dynamic,
            [...$required, ...$optional],
            $complete && $optionalComplete,
            array_fill_keys($optional, true),
        );
    }

    public function merge(self $other): self
    {
        return new self(
            $this->names + $other->names,
            $this->dynamic || $other->dynamic,
            [...$this->includes, ...$other->includes],
            $this->complete && $other->complete,
            $this->optionalIncludes + $other->optionalIncludes,
        );
    }

    /**
     * For a view that exists but can't be found or read.
     */
    public function incomplete(): self
    {
        return new self($this->names, $this->dynamic, $this->includes, false, $this->optionalIncludes);
    }

    /**
     * Whether the client may write this property. A binding whose name is computed,
     * such as `wire:model="{{ $field }}"`, could be any property, so it counts for all.
     */
    public function binds(string $property): bool
    {
        return $this->dynamic || isset($this->names[$property]);
    }

    /**
     * The views these patterns name, and whether all of them name a literal view.
     *
     * @return array{list<string>, bool}
     */
    private static function literalViews(string $blade, string ...$patterns): array
    {
        $views = [];
        $complete = true;

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $blade, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
            foreach ($matches as $match) {
                if (($match[2] ?? null) === null) {
                    $complete = false;
                } else {
                    $views[] = $match[2];
                }
            }
        }

        return [$views, $complete];
    }
}
