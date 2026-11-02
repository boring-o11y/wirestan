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

Public methods are just as exposed. The browser can call them with any arguments,
and Livewire binds a model parameter from whatever id it sends.

These mistakes fail silently: nothing throws, the component works in the browser,
and the IDOR only shows up when someone goes looking for it.

`wirestan` catches them in CI: state the client can tamper with, actions that never
check authorization, cached data shared between users, and validation that never
runs. See [Rules](#rules) for the full list.

## Requirements

- PHP `^8.4`
- PHPStan `^2.0` (works great alongside [Larastan](https://github.com/larastan/larastan))
- Livewire 3 or 4 in the analysed project

## Installation

```sh
composer require --dev boring-o11y/wirestan
```

### Registering the rules

If you use [`phpstan/extension-installer`](https://github.com/phpstan/phpstan-extension-installer)
(recommended), there is **nothing else to do** — the rules register
automatically.

Otherwise, include the bundled `extension.neon` from your `phpstan.neon`
(or `phpstan.neon.dist`):

```neon
includes:
    - vendor/boring-o11y/wirestan/extension.neon
```

That's it — the rules now run as part of your normal analysis:

```sh
vendor/bin/phpstan analyse
```

### Analysing single-file components

Livewire 4's `make:livewire` generates `resources/views/components/⚡name.blade.php`,
so a `paths` setting that only lists `app` never analyses those components. Add the
views directory:

```neon
parameters:
    paths:
        - app
        - resources/views
```

These components are anonymous classes, so messages name them like
`Livewire\Component@anonymous/…/⚡booking.blade.php:7`.

## Rules

| Rule | Identifier | In one line |
| --- | --- | --- |
| [LockedPublicPropertyRule](#lockedpublicpropertyrule) | `boringO11yWirestan.lockedPublicProperty` | Never-reassigned public properties must be `#[Locked]` |
| [PublicReservedPropertyRule](#publicreservedpropertyrule) | `boringO11yWirestan.publicReservedProperty` | `$rules`, `$listeners` and other framework settings must not be public |
| [ComputedCacheScopeRule](#computedcachescoperule) | `boringO11yWirestan.computedCacheScope` | Cached `#[Computed]` values must not depend on the user or on state their cache key ignores |
| [ModelParamWithoutAuthorizationRule](#modelparamwithoutauthorizationrule) | `boringO11yWirestan.modelParamWithoutAuthorization` | Actions that bind a model from a client-supplied id must authorize it |
| [ValidationNeverEnforcedRule](#validationneverenforcedrule) | `boringO11yWirestan.validationNeverEnforced` | Components with validation rules must call `validate()` |
| [LockedUrlConflictRule](#lockedurlconflictrule) | `boringO11yWirestan.lockedUrlConflict` | A property can't be both `#[Locked]` and `#[Url]` |
| [LegacyComputedAccessorRule](#legacycomputedaccessorrule) | `boringO11yWirestan.legacyComputedAccessor` | `getFooProperty()` accessors are client-callable: use `#[Computed]` |
| [AuthorizeTargetRule](#authorizetargetrule) | `boringO11yWirestan.authorizeTarget` | `#[Authorize]` must target something that resolves |

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
  `CannotUpdateLockedPropertyException`. This rule doesn't look inside Form classes
  either, and their fields are client-editable: `$wire.set('form.postId', 2)` works.
  Add `#[Locked]` yourself to any Form field the server controls.
- **`#[Url]` properties.** The visitor sets these from the query string, so they
  are input, and locking one is what
  [`LockedUrlConflictRule`](#lockedurlconflictrule) reports.
- **Framework-reserved properties**: `$listeners`, `$rules`, `$messages`,
  `$validationAttributes`, `$validationCustomValues`, `$queryString`,
  `$paginationTheme`. The fix for these is `protected`, not `#[Locked]`, which
  [`PublicReservedPropertyRule`](#publicreservedpropertyrule) reports. Configurable,
  see below.
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
            - validationCustomValues
            - queryString
            - paginationTheme
            - myOwnConvention
```

### `PublicReservedPropertyRule`

Reports public `$rules`, `$messages`, `$validationAttributes`,
`$validationCustomValues`, `$listeners`, `$queryString` and `$paginationTheme`.
Livewire has no exclusion list for these, so a public one is client-editable like
any other property: `$wire.set('rules', {title: 'nullable'})` switches off your
validation. Make them `protected`. `#[Locked]` and `static` properties are skipped.

```php
class CreatePost extends Component
{
    public array $rules = ['title' => 'required'];     // ← flagged

    protected array $rules = ['title' => 'required'];  // fine
}
```

### `ComputedCacheScopeRule`

Reports cached `#[Computed]` methods whose result depends on something the cache
key doesn't include. Which cache applies decides what counts:

| Attribute | Shared by | Flagged when the method reads |
| --- | --- | --- |
| `cache: true`, or `persist: true` with a `key` | Every visitor | `auth()`, `request()`, `session()`, `cookie()`, the `Auth`, `Request`, `Session`, `Cookie` or `Gate` facades, or any public property |
| `persist: true` | One component instance | A public property the client can change |

```php
#[Computed(cache: true)]
public function myOrders()
{
    return Order::whereBelongsTo(auth()->user())->get();  // ← flagged: every visitor gets the first caller's orders
}

#[Computed(persist: true)]
public function results()
{
    return Post::search($this->search)->get();  // ← flagged: stale once $search changes
}
```

The check follows `$this->` helpers and other computed properties defined on the
class or its traits.

Livewire uses `key` as a literal string, so `key: 'posts-{search}'` is still one
entry shared by everyone.

Not flagged for `persist: true`: `#[Locked]` properties, model properties (Livewire
protects their identity), and methods the class busts with `unset($this->results)`.
Non-public properties are ignored for both cache types, because they aren't persisted
between requests and usually hold fixed values or services.

### `ModelParamWithoutAuthorizationRule`

Reports public actions and `#[On]` handlers with a route-bindable parameter and no
authorization check. Livewire resolves the parameter from whatever id the client
sends, and the client can dispatch events itself, so any record can be named.

```php
public function delete(Post $post)  // ← flagged
{
    $post->delete();
}

#[Authorize('delete', 'post')]      // fine
public function archive(Post $post)
{
    $post->archive();
}
```

Any of these counts as a check, in the action or in a `$this->` helper it calls:

- `#[Authorize]` on the action itself. Livewire ignores it on a helper, because it
  only runs when the client calls that method directly.
- `$this->authorize()`, `->can()`, `->cannot()`, or any `Gate::` call.
- `abort()`, `abort_if()` or `abort_unless()`.
- Throwing `AuthorizationException`.
- An ownership comparison against the current user, such as
  `$post->user_id !== auth()->id()` or `$post->owner->is(auth()->user())`.

A check in `boot()`, `booted()` or `hydrate()` covers every action, because those run
on every request. A check in `mount()` doesn't, because it only runs on the first one.

The rule can't see method bodies on parent classes or route middleware. If your guard
lives on a parent class, list its name:

```neon
parameters:
    wirestan:
        authorizationMethods:
            - ensureOwnership
```

If a route's `can:` middleware already covers the record, ignore the finding by its
identifier.

### `ValidationNeverEnforcedRule`

Reports components with validation rules and at least one action, but no call to
`validate()` or `validateOnly()`. Rules alone don't stop anything: `#[Validate]`
records errors when a property updates, the action still runs afterwards, and
`rules()` does nothing until `validate()` is called.

```php
class CreatePost extends Component  // ← flagged
{
    #[Validate('required')]
    public string $title = '';

    public function save()
    {
        Post::create(['title' => $this->title]);
    }
}
```

The rule checks that a call exists somewhere in the component, not that every action
makes one. It stays quiet on abstract classes and on components with a custom base
class, because the call may live where it can't see. It doesn't follow rules declared
on `Livewire\Form` objects.

### `LockedUrlConflictRule`

Reports properties that are both `#[Locked]` and `#[Url]`, on components and forms.
The lock only blocks client updates. `#[Url]` still writes the query-string value
during mount, so the visitor chooses a value the code treats as server-controlled.

```php
#[Locked]
#[Url]
public int $tenantId = 0;  // ← flagged: ?tenantId=99 sets it to 99
```

### `LegacyComputedAccessorRule`

Reports public `getFooProperty()` accessors. They are ordinary public methods, so the
browser can call them and read the return value. Convert them to `#[Computed]`, which
Livewire refuses to call from the client. Making an accessor `protected` isn't a fix,
because Livewire only recognises public ones.

```php
public function getTotalProperty()  // ← flagged
{
    return $this->order->total;
}

#[Computed]                         // fine, still read as $this->total
public function total()
{
    return $this->order->total;
}
```

### `AuthorizeTargetRule`

Reports `#[Authorize]` targets that don't resolve. Livewire looks the target up at
runtime as a class, then a method parameter, then a property on the component. A typo
reaches the gate as `null`, and an untyped parameter reaches it as the raw client
value, so the check silently runs against the wrong thing.

```php
#[Authorize('update', 'psot')]        // ← flagged: no such class, parameter or property
public function save() {}

#[Authorize('delete', 'comment')]     // ← flagged: needs a type hint
public function remove($comment) {}

#[Authorize('delete', 'comment')]     // fine
public function destroy(Comment $comment) {}
```

Valid property targets are public properties, including nested paths such as
`post.author`, and computed properties.

## Adopting on an existing codebase

The rules will light up on any Livewire codebase that predates them. Generate a
[baseline](https://phpstan.org/user-guide/baseline) so it only fails on *new*
violations, then burn the entries down:

```sh
vendor/bin/phpstan analyse --generate-baseline
```

Each `LockedPublicPropertyRule` entry is a real decision. Add `#[Locked]` where the
property is server-controlled. Leave a genuine `wire:model` input unlocked, because
locking it throws `CannotUpdateLockedPropertyException`. The rule reads PHP, not your
views, so it keeps flagging an input that no PHP code writes to.

To silence one of those, or any other individual finding, use the error identifier:

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
composer phpstan   # analyse the package itself
```

Tests use PHPStan's `RuleTestCase` against fixtures in `tests/Fixtures`, with
minimal `Livewire\*` and `Illuminate\*` stubs in `tests/Stubs` so the package has
no runtime dependency on Livewire itself.

## License

MIT — see [LICENSE](LICENSE).
