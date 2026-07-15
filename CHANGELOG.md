# Changelog

All notable changes to `toniel/laravel-keycloak-socialite` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `ExtendedKeycloakProvider` — custom Socialite provider that reads optional federated identity claims (`google_id`, `google_avatar`) from Keycloak userinfo endpoint
- Support for optional Google identity fields in `HasKeycloakIdentity` trait
- Documentation for configuring Keycloak Identity Provider Mappers to pass Google user data

### Changed

- `KeycloakAuthenticatable::updateKeycloakIdentity()` now accepts optional `$additionalData` parameter for federated identity fields (backward compatible — defaults to empty array)
- `HasKeycloakIdentity::updateKeycloakIdentity()` and `keycloakFillableFromSocialite()` now handle optional Google identity data when available
- `KeycloakSocialiteServiceProvider` now uses manual provider registration instead of event-based registration for better control

### Note

This release is **fully backward compatible**. The Google identity features are **optional** — if Keycloak is not configured to pass Google data, the fields remain `null` without errors.

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
