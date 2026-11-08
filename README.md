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
runs. It also catches Livewire mistakes that only show up at runtime, like resetting a
property that can't be reset or writing to a `#[Reactive]` one. See [Rules](#rules)
for the full list.

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
| [ReactiveMutationRule](#reactivemutationrule) | `boringO11yWirestan.reactiveMutation` | `#[Reactive]` properties can't be written |
| [ComputedMisuseRule](#computedmisuserule) | `boringO11yWirestan.computedMisuse` | `#[Computed]` methods must be usable as computed properties |
| [ResetTargetRule](#resettargetrule) | `boringO11yWirestan.resetTarget` | `reset()` and `pull()` must not target what they can't restore |
| [NonPersistedStateRule](#nonpersistedstaterule) | `boringO11yWirestan.nonPersistedState` | Non-public state set in `mount()` is gone on the next request |
| [AsyncMutationRule](#asyncmutationrule) | `boringO11yWirestan.asyncMutation` | `#[Async]` actions must not write public properties |
| [ReservedActionNameRule](#reservedactionnamerule) | `boringO11yWirestan.reservedActionName` | Actions can't be named after `$wire`'s own methods |

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
list destructuring (`[$this->a, $this->b] = …`), `unset($this->rows[$key])`,
changes through in-place functions like `sort($this->rows)`, assignment by a `foreach`
or a by-reference loop over the property, writes to an object it holds
(`$this->dto->name = …`), and `$this->reset('name')` or `$this->pull('name')` with
literal property names. Methods from traits count.

#### What the rule deliberately skips

- **Properties already marked `#[Locked]`**, obviously.
- **`static` and `readonly` properties** — not client-editable state.
- **`Livewire\Form` properties.** A Form is mutated through nested fields
  (`wire:model="form.email"`) and never reassigned wholesale, so it reads as
  immutable — but locking the root breaks every nested update at runtime with
  `CannotUpdateLockedPropertyException`. This rule doesn't look inside Form classes
  either, and their fields are client-editable: `$wire.set('form.postId', 2)` works.
  Add `#[Locked]` yourself to any Form field the server controls.
- **Properties bound in a view.** Locking a `wire:model` input throws
  `CannotUpdateLockedPropertyException`, so the rule reads the component's views. See
  [How views are found](#how-views-are-found).
- **Properties with an input attribute**: `#[Validate]`, `#[Rule]`, `#[Modelable]` and
  `#[Session]` properties are set by the client, and locking them throws too.
  `#[Reactive]` ones can't be set by the client at all.
- **Properties with validation rules** in `$rules`, `rules()`, or a literal `validate()`
  or `validateOnly()` call, for the same reason.
- **Properties with an update hook** such as `updatedTitle()`, including nested ones
  such as `updatedItemsName()`. Livewire calls those when the client updates the property.
- **Models and Eloquent collections**, by native type. Livewire ignores client writes to
  these: `set('post', 2)` keeps the record and `set('post.id', 2)` throws.
- **`#[Url]` properties.** The visitor sets these from the query string, so they
  are input, and locking one is what
  [`LockedUrlConflictRule`](#lockedurlconflictrule) reports. The same goes for
  properties named in the legacy `$queryString` or `queryString()`, which Livewire
  binds to the URL the same way.
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

#### How views are found

The rule looks for a component's Blade source the way Livewire does:

- A single-file component's view is the rest of its file.
- A multi-file component's view is the `.blade.php` file next to its `.php` file.
- Otherwise it reads what `render()` returns: an inline template, a literal `view('x')`,
  `view()->make('x')` or `View::make('x')`.
- With no `render()`, it reads `livewire/<kebab-name>.blade.php`, using the part of the
  class name after `Livewire\`.

It follows `@include`, `@includeIf`, `@includeWhen`, `@includeUnless`, `@includeFirst`
and `@each` when the view name is a literal.

These count as binding a property: `wire:model` with any modifiers, `$set`, `$toggle`,
`$wire.set`, `$wire.entangle`, `@entangle`, `$wire.upload`, `$wire.x = …`,
`x-model="$wire.x"`, and `wire:click="x++"`. A binding whose name is computed, such as
`wire:model="{{ $field }}"`, could be any property, so the rule stays quiet on the whole
component. Commented-out bindings don't count.

If it can't read every view, such as a `pages::show` whose namespace isn't configured
or a view name held in a variable, the rule still reports the property and adds a tip
about it. It can't see:

- A binding inside a Blade component's own template, such as a literal `wire:model` in
  `components/email-input.blade.php`. One passed through as `<x-input wire:model="x">`
  is fine.
- A child component's `wire:model="$parent.x"`, which binds a property of the parent.

Views are read from `resources/views` under the directory you run PHPStan in. Change
that with `viewPaths`:

```neon
parameters:
    wirestan:
        viewPaths:
            - %currentWorkingDirectory%/resources/views
            - %currentWorkingDirectory%/modules/billing/views
```

A namespaced view, such as `billing::invoice` or Livewire's `pages::show`, is read from
the directories you list for its namespace, in order. Mirror what your app passes to
`loadViewsFrom()` or `View::addNamespace()`:

```neon
parameters:
    wirestan:
        viewNamespaces:
            billing:
                - %currentWorkingDirectory%/modules/billing/views
            pages:
                - %currentWorkingDirectory%/resources/views/pages
```

On PHPStan 2.3 or later, editing a view re-analyses only the components that read it.
Older versions can't track this, so run `vendor/bin/phpstan clear-result-cache` after
changing a binding.

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

### `ReactiveMutationRule`

Reports writes to `#[Reactive]` properties. The value belongs to the parent, and any
change throws `CannotMutateReactivePropException`, including in `mount()`, `boot()`,
`hydrate()` and `render()`.

```php
#[Reactive]
public int $count = 0;

public function mount(): void
{
    $this->count = 1;  // ← flagged
}

public function clear(): void
{
    $this->reset();    // ← flagged: resets $count too
}
```

Writes in `__construct()` and `dehydrate()` aren't flagged, because neither throws. A
`fill()` whose keys aren't literal isn't flagged either, because its targets are unknown.

### `ComputedMisuseRule`

Reports `#[Computed]` methods that can't work as computed properties:

```php
public string $total = '';

#[Computed]
public function total(): int      // ← flagged: the property answers $this->total first
{
    return 1;
}

#[Computed]
public function rows(Repo $repo)  // ← flagged: throws ArgumentCountError
{
    return $repo->all();
}

public function export()
{
    return $this->rows();         // ← flagged: runs it again, use $this->rows
}
```

Livewire doesn't inject dependencies into computed methods, so a class-typed parameter
fails like any other. A same-named property of any visibility hides the method, unless
it's static. `#[Computed]` on a `Livewire\Form` is flagged too: Livewire ignores it there,
and reading `$form->name` throws. `$this->rows(5)` isn't flagged, because calling it is
the only way to pass an argument.

### `ResetTargetRule`

Reports `reset()`, `pull()` and `resetExcept()` calls that throw, either straight away
or on the next read of the property.

```php
public string $title;          // typed, no default
public Post $post;             // typed, no default
protected string $token = '';

public function clear()
{
    $this->reset('titel');     // ← flagged: ReflectionException, no such property
    $this->reset('token');     // ← flagged: Error, non-public property
    $this->reset('title');     // ← flagged: unset, so the next read throws
    $this->reset();            // ← flagged: unsets $title and $post
}
```

Livewire restores each property from a fresh instance, so a typed property with no
default has nothing to restore and gets unset. `Livewire\Form` properties are exempt,
because Livewire resets their fields instead. So is a property the same method assigns,
or the constructor sets. Only literal names are checked, and calls on Form objects aren't.

### `NonPersistedStateRule`

Reports non-public properties set in `mount()` and read on later requests. Only public
properties travel in the snapshot, so the value is gone after the first request.

```php
protected Client $client;

public function mount(Client $client)
{
    $this->client = $client;  // ← flagged
}

public function send()
{
    $this->client->post();    // Error: must not be accessed before initialization
}
```

Set it in `boot()` or `hydrate()` instead, which run on every request. A write in any
method that runs after mount counts as re-seeding it, such as `$this->client ??= …` in
a helper. A value only read during mount, by `mount()` or a non-public helper only it
calls, isn't flagged. The rule stays quiet on properties declared on a parent class and
on components with a dynamic `$this->{$name} = …` write.

### `AsyncMutationRule`

Reports `#[Async]` actions that write public properties, directly or through a `$this->`
helper. Async actions run in parallel from the same starting state, so concurrent calls
overwrite each other, and Livewire's docs say never to use them this way.

```php
#[Async]
public function increment()
{
    $this->count++;  // ← flagged: five quick clicks can add up to one
}

#[Async]
public function track()
{
    Analytics::track('viewed', $this->url);  // fine
}
```

Writes to non-public properties and to a model's attributes are ignored, because neither
is in the snapshot. The rule reads the attribute only, so it doesn't see `wire:click.async`.

### `ReservedActionNameRule`

Reports actions named like one of `$wire`'s own methods, such as `upload`, `get`, `set`,
`call`, `on`, `watch` or `commit`. `wire:click="upload"` runs `$wire.upload`, and `$wire`
resolves its own names before calling the server, so the action never runs and nothing
reports why.

```php
public function upload()       // ← flagged
{
    $this->photo->store('photos');
}

public function uploadPhoto()  // fine
{
    $this->photo->store('photos');
}
```

The list comes from Livewire 4, so a few names, such as `dirty` and `island`, are only
reserved from that version on. Overrides of methods from a parent class, such as
`dispatch()`, aren't flagged.

## Adopting on an existing codebase

The rules will light up on any Livewire codebase that predates them. Generate a
[baseline](https://phpstan.org/user-guide/baseline) so it only fails on *new*
violations, then burn the entries down:

```sh
vendor/bin/phpstan analyse --generate-baseline
```

Each `LockedPublicPropertyRule` entry is a real decision. Add `#[Locked]` where the
property is server-controlled. Leave a genuine `wire:model` input unlocked, because
locking it throws `CannotUpdateLockedPropertyException`. The rule reads your views to
recognise those, but it [can't see every binding](#how-views-are-found).

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

Developing the package needs PHPStan 2.3 or later. Tests use PHPStan's `RuleTestCase`
against fixtures in `tests/Fixtures`, with
minimal `Livewire\*` and `Illuminate\*` stubs in `tests/Stubs` so the package has
no runtime dependency on Livewire itself.

## License

MIT — see [LICENSE](LICENSE).
