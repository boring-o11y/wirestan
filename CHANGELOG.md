# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
