# wirestan

[![Latest Version](https://img.shields.io/packagist/v/boring-o11y/wirestan.svg)](https://packagist.org/packages/boring-o11y/wirestan)
[![License](https://img.shields.io/packagist/l/boring-o11y/wirestan.svg)](LICENSE)

PHPStan / [Larastan](https://github.com/larastan/larastan) rules for **Livewire components**.

Every public property on a Livewire component is part of the client-editable
state: the browser can set any of them with `$wire.set('prop', value)`, and
Livewire will happily hydrate the new value on the next request. That is exactly
what you want for a `wire:model`-bound input — and exactly what you *don't* want
for `$bookingId`, `$tenantId`, `$role`, or anything else the server seeded in
`mount()` and then trusted.

Livewire's answer is the `#[Locked]` attribute, which makes the property
server-only. The failure mode of forgetting it is silent: nothing throws, the
component works in the browser, and the IDOR only shows up when someone goes
looking for it.

`wirestan` catches the omission in CI.

## Requirements

- PHP `^8.2`
- PHPStan `^2.0` (works great alongside [Larastan](https://github.com/larastan/larastan))
- Livewire 3 or 4 in the analysed project

## Installation

```sh
composer require --dev boring-o11y/wirestan
```

### Registering the rules

If you use [`phpstan/extension-installer`](https://github.com/phpstan/phpstan-extension-installer)
(recommended), there is **nothing else to do** — the rule registers
automatically.

Otherwise, include the bundled `extension.neon` from your `phpstan.neon`
(or `phpstan.neon.dist`):

```neon
includes:
    - vendor/boring-o11y/wirestan/extension.neon
```

That's it — the rule now runs as part of your normal analysis:

```sh
vendor/bin/phpstan analyse
```

## Rules

| Rule | Identifier | In one line |
| --- | --- | --- |
| [LockedPublicPropertyRule](#lockedpublicpropertyrule) | `boringO11yWirestan.lockedPublicProperty` | Never-reassigned public properties must be `#[Locked]` |

### `LockedPublicPropertyRule`

Reports public properties of `Livewire\Component` subclasses that are never
reassigned outside the lifecycle *seed* methods and are missing
`#[Livewire\Attributes\Locked]`.

```php
use Livewire\Component;

class ShowBooking extends Component
{
    public int $bookingId = 0;   // ← flagged

    public string $note = '';    // fine: reassigned by saveNote()

    public function mount(int $bookingId): void
    {
        $this->bookingId = $bookingId;
    }

    public function saveNote(string $note): void
    {
        $this->note = $note;
    }
}
```

```
Livewire public property ShowBooking::$bookingId is never reassigned outside
lifecycle methods. It must be marked #[Livewire\Attributes\Locked] — otherwise
the client can mutate it via $wire.set.
```

The fix is one attribute:

```php
use Livewire\Attributes\Locked;

#[Locked]
public int $bookingId = 0;
```

#### What counts as a mutation

Writes inside the **seed methods** — `mount`, `__construct`, `boot`, `booted`,
`hydrate`, `dehydrate` — are server-controlled initialisation, not user-driven
mutation, so they do *not* exempt a property. A write anywhere else does.

Recognised as a mutation: plain assignment, compound assignment (`.=`, `+=`, …),
reference assignment, `++`/`--`, array-element writes (`$this->rows[] = …`),
list destructuring (`[$this->a, $this->b] = …`), and `$this->reset('name')`
with literal property names.

#### What the rule deliberately skips

- **Properties already marked `#[Locked]`**, obviously.
- **`static` and `readonly` properties** — not client-editable state.
- **`Livewire\Form` properties.** A Form is mutated through nested fields
  (`wire:model="form.email"`) and never reassigned wholesale, so it reads as
  immutable — but locking the root breaks every nested update at runtime with
  `CannotUpdateLockedPropertyException`. Livewire de/hydrates Forms natively, so
  they are not a `$wire.set` vector.
- **Framework-reserved properties**: `$listeners`, `$rules`, `$messages`,
  `$validationAttributes`, `$queryString`, `$paginationTheme`. Configurable, see
  below.
- **The entire component**, when a non-seed method performs an *opaque
  mass-assignment* that could touch any public property: `$this->fill()`,
  `fillData()`, `mergeData()`, `resetExcept()`, a no-argument or
  non-literal-argument `$this->reset()`, or a dynamic write `$this->{$name} = …`.
  Immutability can no longer be proven statically, so the rule stays quiet rather
  than emit false positives.

#### Configuration

Override the reserved-property list from your `phpstan.neon`:

```neon
parameters:
    wirestan:
        reservedProperties:
            - listeners
            - rules
            - messages
            - validationAttributes
            - queryString
            - paginationTheme
            - myOwnConvention
```

## Adopting on an existing codebase

The rule will light up on any Livewire codebase that predates it. Generate a
[baseline](https://phpstan.org/user-guide/baseline) so it only fails on *new*
violations, then burn the entries down:

```sh
vendor/bin/phpstan analyse --generate-baseline
```

Each baselined entry is a real decision: add `#[Locked]` where the property is
server-controlled, or confirm it is genuinely a `wire:model`-bound input and
remove it from the baseline once the rule stops matching.

To silence an individual finding, use the error identifier:

```neon
parameters:
    ignoreErrors:
        -
            identifier: boringO11yWirestan.lockedPublicProperty
            path: app/Livewire/SomeComponent.php
```

## Development

```sh
composer install
composer test      # phpunit
composer phpstan   # analyse the rule itself
```

Tests use PHPStan's `RuleTestCase` against fixtures in `tests/Fixtures`, with
minimal `Livewire\*` stubs in `tests/Stubs` so the package has no runtime
dependency on Livewire itself.

## License

MIT — see [LICENSE](LICENSE).
