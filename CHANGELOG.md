# Changelog

All notable changes to `toniel/laravel-keycloak-socialite` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Documentation

- Expand the backchannel logout setup: why front-channel logout must be off, how to make a containerised Keycloak reach the app, a "Verifying it works" table mapping each log line to its cause, and the `keycloak_id` requirement
- Note that "Backchannel logout session required" is optional — the package matches users by `sub` and ignores `sid`

## [1.4.1] — 2026-09-03

### Fixed

- Logging out now stamps the silent SSO check, so the next page load does not immediately sign the user back in. This mattered most with `logout.mode` = `local`, where the Keycloak SSO session survives the logout and the check would have undone it.

## [1.4.0] — 2026-09-03

### Added

- **Silent SSO check (`prompt=none`)** — guests are signed in automatically when a Keycloak SSO session already exists, on any page including public ones. Enable with `KEYCLOAK_SILENT_SSO=true`.
- Route `GET /auth/keycloak/silent-check` (`login.keycloak.silent-check`)
- Middleware `AttemptKeycloakSso`, aliased as `keycloak.sso`, pushed onto the `web` group when `silent_sso.auto_apply` is `true`
- Support class `SilentSso` holding the session keys and retry rule
- The middleware is registered through the HTTP kernel and slotted into the middleware priority list ahead of `auth`, so it survives the kernel's group sync and runs before guests are redirected to the login page
- The silent check skips the `login` route when `routes.auto_login_redirect` is enabled — that route already forwards to Keycloak interactively
- Config: `silent_sso.enabled`, `silent_sso.auto_apply`, `silent_sso.route`, `silent_sso.route_as`, `silent_sso.retry_after`, `silent_sso.except`

### Changed

- The callback controller now handles `error=...` responses from the authorization endpoint. `login_required` (and the other no-session OIDC errors) during a silent check returns the guest to their page quietly; other errors still redirect to `login` with a flash message and a `KeycloakAuthenticationFailed` event.

### Documentation

- Explain why cross-app SSO does not kick in on its own, and what can trigger it, in "Single Sign-On Setup"
- Add a "Troubleshooting" section covering a guest session that survives a login elsewhere, silent checks that never sign anyone in, redirect loops, and a middleware that never runs

## [1.3.2] — 2026-08-29

### Documentation

- Reframe "Skip the Keycloak login page" as two equivalent options: app-side `kc_idp_hint` (`KEYCLOAK_IDP_HINT`) and Keycloak-side "Authenticate by default"

## [1.3.1] — 2026-08-29

### Documentation

- Add a "Single Sign-On Setup" section covering cross-app SSO (login once, access all apps) and skipping the Keycloak login page via the IdP "Authenticate by default" setting
- Add a "Keycloak Client Setup" section covering client creation, client-secret generation, and required redirect / post-logout / web-origin settings

## [1.3.0] — 2026-08-28

### Added

- **Auto login redirect** — optional `routes.auto_login_redirect` config (default `false`) that registers a `login` named route forwarding guests straight to the Keycloak authorization endpoint, so unauthenticated users skip the app's own login page
- Config: `routes.auto_login_redirect`, `routes.login`, `routes.login_as`

### Documentation

- Use Composer's `-W` option during installation so applications with Guzzle 8 locked can resolve Laravel Socialite's supported Guzzle 7 release
- Document the targeted update command for existing installations affected by a partial Composer update

## [1.2.0] — 2026-07-29

### Added

- **Backchannel Logout** support — Keycloak POSTs `logout_token` to `/auth/keycloak/backchannel-logout` when a user logs out from any app, destroying local sessions across all clients automatically
- `HandleBackchannelLogoutController` — decodes JWT logout_token, finds user by `keycloak_id` (`sub`), deletes sessions from database
- Config: `logout.backchannel_enabled` (default `true`)
- Config: `routes.backchannel_logout` path customization

### Changed

- **Logout controller rewritten** — no longer depends on Socialite driver instance at logout time; builds Keycloak logout URL directly from config
- `post_logout_redirect_uri` now always resolves to absolute URL (prefixes `APP_URL` if relative path configured)
- Default logout mode changed from `local` to `keycloak` (more appropriate for SSO ecosystems)
- `id_token_hint` sent automatically for silent logout (no Keycloak confirmation page)

### Fixed

- "Invalid redirect uri" error on Keycloak logout — caused by sending relative path instead of absolute URL as `post_logout_redirect_uri`

## [1.1.0] — 2026-07-27

### Added

- Laravel 13 support (`illuminate/contracts ^13.0`, `illuminate/support ^13.0`)
- `KeycloakWithIdToken` provider — captures `id_token` from token endpoint response for silent logout
- Logout mode config (`keycloak-socialite.logout.mode`): `local` or `keycloak`
- `id_token_hint` config for silent Keycloak logout without confirmation page
- ServiceProvider registers custom `KeycloakWithIdToken` provider instead of base Keycloak provider

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
