# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `ReactiveMutationRule`: `#[Reactive]` properties can't be written, including in `mount()` and `boot()`.
- `ComputedMisuseRule`: `#[Computed]` methods with required parameters, hidden by a same-named property, called directly, or on a `Livewire\Form`.
- `ResetTargetRule`: `reset()`, `pull()` and `resetExcept()` calls that target an unknown or non-public property, or unset a typed property with no default.
- `NonPersistedStateRule`: non-public properties set in `mount()` and read on later requests.
- `AsyncMutationRule`: `#[Async]` actions must not write public properties.
- `ReservedActionNameRule`: actions can't be named after `$wire`'s own methods, such as `upload` or `get`.
- `wirestan.viewPaths` parameter, for where to find the Blade views of a component.

### Fixed

- `LockedPublicPropertyRule` no longer flags most client input, where following its advice throws `CannotUpdateLockedPropertyException`. It now skips:
  - properties bound in the component's views, with `wire:model` and similar;
  - `#[Validate]`, `#[Rule]`, `#[Modelable]`, `#[Reactive]` and `#[Session]` properties;
  - properties named in `$rules`, `rules()`, or a literal `validate()` or `validateOnly()` call;
  - properties with an `updated*` or `updating*` hook;
  - models and Eloquent collections, which ignore client writes.
- `LockedPublicPropertyRule` now counts writes it missed: `unset($this->rows[$key])`, in-place functions like `sort($this->rows)`, `foreach` assignment, by-reference loops and references, `$this->dto->name = …`, `$this->pull('name')`, and any write from a trait method.

### Changed

- `LockedPublicPropertyRule` adds a tip when it can't read every view a component renders. Finding messages and identifiers are unchanged, so baselines keep working.
- Developing the package now needs PHPStan 2.3 or later. Projects using the rules can stay on any 2.x, but below 2.3 they need `phpstan clear-result-cache` after changing a binding in a view.

## [0.2.0] - 2026-11-02

### Added

- `PublicReservedPropertyRule`: `$rules`, `$listeners` and other framework settings must not be public, because the client can overwrite them.
- `ComputedCacheScopeRule`: cached `#[Computed]` values must not depend on the user or on state their cache key ignores.
- `ModelParamWithoutAuthorizationRule`: public actions and `#[On]` handlers that bind a model from a client-supplied id must authorize it.
- `ValidationNeverEnforcedRule`: components with validation rules and actions must call `validate()` or `validateOnly()`.
- `LockedUrlConflictRule`: a property can't be both `#[Locked]` and `#[Url]`.
- `LegacyComputedAccessorRule`: public `getFooProperty()` accessors are client-callable and should become `#[Computed]` methods.
- `AuthorizeTargetRule`: `#[Authorize]` targets must resolve to a class, a typed parameter, or a public or computed property.
- `wirestan.authorizationMethods` parameter, for naming your own authorization guards.

### Changed

- `LockedPublicPropertyRule` no longer flags `#[Url]` properties, since locking them is what `LockedUrlConflictRule` reports.
- `$validationCustomValues` is now in the default `wirestan.reservedProperties` list.

### Removed

- Support for PHP 8.2 and 8.3. The package now requires PHP 8.4 or later, and CI runs on PHP 8.4 and 8.5.
