# Changelog

All notable changes to `toniel/laravel-keycloak-socialite` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] — 2026-07-27

### Added

- Laravel 13 support (`illuminate/contracts` ^13.0, `illuminate/support` ^13.0)

### Changed

- Widened package constraints to `^12.0|^13.0` for illuminate components
- Updated dev dependencies: `orchestra/testbench ^10.0|^11.0`, `phpunit ^11.0|^12.0`
- Migrated test annotations from `/** @test */` to `#[Test]` attribute (PHPUnit 12 compatibility)

## [1.0.0] — 2026-07-15

### Added

- Keycloak redirect controller with configurable `kc_idp_hint`
- Callback controller with user lookup, auto-registration, and login
- Keycloak SSO logout (Laravel session + Keycloak logout endpoint)
- `KeycloakAuthenticatable` contract for decoupled User model integration
- `HasKeycloakIdentity` trait with default implementations
- Three events: `KeycloakUserAuthenticated`, `KeycloakUserCreated`, `KeycloakAuthenticationFailed`
- Auto-loading routes (redirect, callback, logout) — disableable via config
- Publishable config file (`keycloak-socialite.php`)
- Publishable migration stub for `keycloak_id` and `keycloak_avatar` columns
- Event-driven redirect URL override (pass-by-reference pattern)
- Configurable column names for `keycloak_id` and `keycloak_avatar`
- Auto-registration toggle (`KEYCLOAK_AUTO_REGISTER`)
- Full test suite (8 tests covering redirect, callback, creation, rejection, exceptions, event overrides, custom redirect URLs, and logout)
