# Laravel Keycloak Socialite

Reusable Keycloak Socialite authentication for Laravel applications. Provides a drop-in Keycloak SSO integration with redirect, callback, logout, and **backchannel logout** routes — configurable and decoupled from any specific User model or permission system.

## Features

- **SSO Login** — Redirect to Keycloak, callback with auto-register
- **Silent Logout** — Logout from Keycloak without confirmation page (via `id_token_hint`)
- **Backchannel Logout** — When user logs out from any app, all other apps in the ecosystem are logged out automatically
- **Cross-app SSO** — Login once, access all apps in the same Keycloak realm
- **Silent SSO check** — Guests are signed in automatically from an existing Keycloak session, with no visible login step (`prompt=none`, opt-in)
- **Configurable IDP Hint** — Skip Keycloak login screen, go directly to Google/GitHub/etc
- **Auto login redirect** — Register a `login` route that forwards guests straight to Keycloak (opt-in)
- **Event-driven** — Hook into login, registration, and failure events

## Requirements

- PHP 8.2+
- Laravel 12+ / 13+
- A running [Keycloak](https://www.keycloak.org/) server (tested with v26+)

## Keycloak Client Setup

Create one OpenID Connect client per app in Keycloak, generate its client
secret, and copy the values into `.env`.

### 1. Create the client

**Realm → Clients → Create client**

- **Client type**: OpenID Connect
- **Client ID**: e.g. `admission-login`
- Next

**Capability config:**

| Setting | Value | Notes |
|---------|-------|-------|
| Client authentication | **ON** | Required — this is what exposes the **client secret** |
| Standard flow | **ON** | Authorization Code flow (required) |
| Implicit flow | OFF | |
| Direct access grants | OFF | |

**Login settings:**

- **Root URL**: `http://your-app.test`
- **Valid redirect URIs**: `http://your-app.test/auth/keycloak/callback`
- **Valid post logout redirect URIs**: `http://your-app.test/*`
- **Web origins**: `http://your-app.test`
- Save

### 2. Generate the client secret

**Clients → [your client] → Credentials → Client secret → Regenerate**, then
copy the value into `.env`:

```env
KEYCLOAK_CLIENT_SECRET=<copied-secret>
```

> `KEYCLOAK_REDIRECT_URI` in `.env` must match one of the "Valid redirect URIs"
> exactly.

### 3. Backchannel logout (recommended for SSO)

For cross-app logout, turn **off** front-channel logout and set the backchannel
logout URL — see [Backchannel Logout](#backchannel-logout) for the full setup,
including the two things that silently stop it working (front-channel logout
left on, and Keycloak being unable to reach your app's host).

## Installation

```bash
composer require toniel/laravel-keycloak-socialite -W
```

The `-W` (`--with-all-dependencies`) option lets Composer select Guzzle 7 when the
application lock file currently contains Guzzle 8. Laravel Socialite 5 supports
Guzzle 6 and 7, but does not yet support Guzzle 8.

If the package is already listed in `composer.json`, update it together with
Guzzle and its related packages:

```bash
composer update toniel/laravel-keycloak-socialite laravel/socialite guzzlehttp/guzzle guzzlehttp/promises guzzlehttp/psr7 -W
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=keycloak-socialite-config
```

Publish the migration (optional — only if your users table doesn't already have the columns):

```bash
php artisan vendor:publish --tag=keycloak-socialite-migrations
php artisan migrate
```

## Environment Variables

Add these to your `.env` file:

```env
KEYCLOAK_BASE_URL=https://auth.example.com
KEYCLOAK_REALM=your-realm
KEYCLOAK_CLIENT_ID=your-client-id
KEYCLOAK_CLIENT_SECRET=your-client-secret
KEYCLOAK_REDIRECT_URI=http://your-app.test/auth/keycloak/callback

# Optional
KEYCLOAK_IDP_HINT=google              # Go straight to Google (skip the Keycloak login page). Alt: Keycloak "Authenticate by default" — see Single Sign-On Setup
KEYCLOAK_AUTO_REGISTER=true           # Create users automatically on first login
KEYCLOAK_REMEMBER_LOGIN=false         # Disable for SSO — let Keycloak manage session
KEYCLOAK_REDIRECT_URL=/dashboard      # Fallback post-login redirect

# Silent SSO (log guests in automatically from an existing Keycloak session)
KEYCLOAK_SILENT_SSO=true              # Enable the prompt=none check
KEYCLOAK_SILENT_SSO_AUTO_APPLY=true   # Apply the middleware to the whole `web` group
KEYCLOAK_SILENT_SSO_RETRY_AFTER=600   # Seconds before re-checking a guest (0 = once per session)

# Logout
KEYCLOAK_LOGOUT_MODE=keycloak         # 'keycloak' (destroy SSO session) or 'local' (app only)
KEYCLOAK_LOGOUT_REDIRECT=/            # Where to redirect after logout (must be registered in Keycloak)
KEYCLOAK_BACKCHANNEL_LOGOUT=true      # Enable backchannel logout endpoint
```

## Setup Your User Model

Your User model must implement `KeycloakAuthenticatable`. Use the included trait for defaults:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Toniel\LaravelKeycloakSocialite\Contracts\KeycloakAuthenticatable;
use Toniel\LaravelKeycloakSocialite\Traits\HasKeycloakIdentity;

class User extends Authenticatable implements KeycloakAuthenticatable
{
    use HasKeycloakIdentity;

    protected $fillable = [
        'name', 'email', 'password', 'keycloak_id', 'keycloak_avatar',
    ];
}
```

## Routes

The package automatically registers these routes:

| Method | Default URI | Named Route | Description |
|--------|-------------|-------------|-------------|
| GET | `/auth/keycloak` | `login.keycloak` | Redirect to Keycloak login |
| GET | `/auth/keycloak/callback` | `login.keycloak.callback` | Handle OAuth callback |
| GET | `/auth/keycloak/logout` | `logout.keycloak` | Logout from app + Keycloak |
| GET | `/auth/keycloak/silent-check` | `login.keycloak.silent-check` | Silent SSO check (`prompt=none`) |
| POST | `/auth/keycloak/backchannel-logout` | `login.keycloak.backchannel-logout` | Receive logout signal from Keycloak |
| GET | `/login` | `login` | Forward guests to Keycloak (only when `routes.auto_login_redirect` is `true`) |

To disable auto-registration and define your own routes, set `routes.enabled` to `false` in config.

> When `routes.auto_login_redirect` is enabled, the package registers the `login` named route. Remove any `/login` route your app defines to avoid a duplicate route name/URI conflict.

## Logout

### How Logout Works

When a user logs out:

1. **Local session destroyed** — Laravel session + remember token invalidated
2. **Redirect to Keycloak** — with `id_token_hint` for silent logout (no confirmation page)
3. **Keycloak destroys SSO session** — user is logged out from Keycloak
4. **Backchannel notification** — Keycloak POSTs to all other apps to destroy their sessions
5. **Redirect back** — user lands on your app's home/login page

```
User clicks Logout in App A
  → App A destroys local session
  → Redirect to Keycloak logout (silent — no confirmation page)
  → Keycloak destroys SSO session
  → Keycloak POSTs logout_token to App B, App C, etc (backchannel)
  → App B, App C destroy their local sessions
  → Keycloak redirects back to App A's post_logout_redirect_uri
  → ✅ User is logged out everywhere
```

### Silent Logout (no Keycloak page)

The package automatically captures and stores the `id_token` during login. On logout, it sends `id_token_hint` to Keycloak — this tells Keycloak to process the logout immediately without showing a confirmation page.

**Requirements:**
- `KEYCLOAK_LOGOUT_MODE=keycloak`
- User must have logged in via the Keycloak callback (not via remember cookie)

### Backchannel Logout

Backchannel logout enables **cross-app session kill**. When a user logs out from any app in your ecosystem, Keycloak notifies all other apps to destroy that user's session.

**How it works:**
- Keycloak sends a `POST` request with a `logout_token` (JWT) to each app's backchannel endpoint
- The package decodes the token, finds the user by their `keycloak_id`, and deletes their sessions from the database

**Keycloak Admin Setup** (per client):

1. Go to **Clients → [your client] → Settings → Logout settings**
2. Turn OFF **"Front-channel logout"**
3. Set **"Backchannel logout URL"**: `https://your-app.com/auth/keycloak/backchannel-logout`
4. **"Backchannel logout session required"** — optional, either setting works.
   ON makes Keycloak include a `sid` claim; the package matches users by `sub`
   and ignores `sid`, destroying every local session that belongs to the user.

Step 2 is the one that silently breaks everything: while front-channel logout
is ON, Keycloak logs the client out through the browser and **never calls the
backchannel URL**, however correctly that URL is configured.

> **Note:** Keycloak must reach the URL from *its own* network, not from your
> browser. If it runs in a container, `https://your-app.test` is resolved
> inside that container — and a container that inherits your host's
> `/etc/hosts` will map it to its own loopback, where nothing is listening.
> Point it at the host instead:
> ```yaml
> services:
>   keycloak:
>     extra_hosts:
>       - "your-app.test:host-gateway"
> ```
> A container only writes `/etc/hosts` when it is **created**, so recreate it
> (`docker compose up -d --force-recreate keycloak`) — restarting is not
> enough.

**Supported session drivers:** `database` (recommended). For `file` driver, the package invalidates the remember token as fallback.

**Requirement:** the user's `keycloak_id` must be stored locally — that column
is what the `sub` claim is matched against. Users created before the package
was installed have to log in once for it to be filled in.

#### Verifying it works

Log out from one app, then read the *other* app's log:

```
Backchannel logout received       {"sub":"b423faec-…","sid":"PFlHrJl-…"}
Backchannel logout: destroyed sessions  {"user_id":4,"sessions_deleted":1}
```

Both lines appearing means the whole path works. What each symptom means:

| Symptom | Cause |
|---------|-------|
| No log line at all | Keycloak never sent it: front-channel logout is still ON, or it cannot reach the URL. Check Keycloak's own log for `KC-SERVICES0057: Logout for client '…' failed` — it names the host and port it tried |
| `user not found locally` | No user with that `keycloak_id`; the `sub` does not match any row |
| `destroyed sessions … "sessions_deleted":0` | Delivered and matched, but that app had no active session — not a failure |
| `missing logout_token` | Something other than Keycloak POSTed to the endpoint |
| The endpoint returns 500 | The handler logs on every call, so an unwritable `storage/logs` throws before sessions are destroyed. Make the log writable by the web-server user |

The other app's UI does not change by itself — the session row is deleted
server-side, so the user is logged out on their **next** request there.

> If the user seems to be signed straight back in afterwards, backchannel
> logout is not the culprit: the IdP session (Google, etc.) survives a Keycloak
> logout, so any page that forces a login will silently re-authenticate them.
> See [Google/IDP Session Behavior](#googleidp-session-behavior) — keep at
> least one page reachable without login.

### Logout Configuration

| ENV | Default | Description |
|-----|---------|-------------|
| `KEYCLOAK_LOGOUT_MODE` | `keycloak` | `keycloak` = destroy SSO session, `local` = app session only |
| `KEYCLOAK_LOGOUT_REDIRECT` | `/` | Post-logout redirect URI (must be absolute or will be prefixed with APP_URL) |
| `KEYCLOAK_LOGOUT_ID_TOKEN_HINT` | `true` | Send id_token for silent logout |
| `KEYCLOAK_BACKCHANNEL_LOGOUT` | `true` | Enable/disable backchannel endpoint |

**Keycloak client configuration required:**
- **Valid Post Logout Redirect URIs**: `https://your-app.com/*`

## Events

### `KeycloakUserAuthenticated`

Fired when an existing user logs in via Keycloak.

```php
use Toniel\LaravelKeycloakSocialite\Events\KeycloakUserAuthenticated;

Event::listen(KeycloakUserAuthenticated::class, function (KeycloakUserAuthenticated $event) {
    // $event->user           — the Eloquent User model
    // $event->socialiteUser  — the Laravel\Socialite User

    // Override the redirect URL:
    $event->redirectUrl = '/custom-dashboard';
});
```

### `KeycloakUserCreated`

Fired when a new user is created from Keycloak data.

```php
use Toniel\LaravelKeycloakSocialite\Events\KeycloakUserCreated;

Event::listen(KeycloakUserCreated::class, function (KeycloakUserCreated $event) {
    $event->user->assignRole('employee');
});
```

### `KeycloakAuthenticationFailed`

Fired when authentication fails.

```php
use Toniel\LaravelKeycloakSocialite\Events\KeycloakAuthenticationFailed;

Event::listen(KeycloakAuthenticationFailed::class, function (KeycloakAuthenticationFailed $event) {
    // $event->reason, $event->exception
    $event->errorRedirect = '/custom-error-page';
});
```

## Configuration Reference

| Key | Default | Description |
|-----|---------|-------------|
| `user_model` | `App\Models\User` | FQCN of your User model |
| `redirect_url` | `/dashboard` | Fallback post-login redirect |
| `idp_hint` | `google` | Keycloak `kc_idp_hint` parameter (set empty to disable) |
| `auto_register` | `true` | Create users on first login |
| `remember_login` | `false` | Disable for SSO apps (let Keycloak manage session) |
| `routes.enabled` | `true` | Auto-register routes |
| `routes.auto_login_redirect` | `false` | Register a `login` route that redirects to Keycloak |
| `routes.login` | `login` | Guest login route URI |
| `routes.login_as` | `login` | Guest login route name |
| `silent_sso.enabled` | `false` | Log guests in automatically from an existing Keycloak SSO session |
| `silent_sso.auto_apply` | `true` | Push the middleware onto the `web` group |
| `silent_sso.retry_after` | `600` | Seconds before re-checking a guest (0 = once per session) |
| `silent_sso.except` | `[]` | Extra URI patterns to skip |
| `silent_sso.route` | `auth/keycloak/silent-check` | Silent check route URI |
| `silent_sso.route_as` | `login.keycloak.silent-check` | Silent check route name |
| `logout.mode` | `keycloak` | `keycloak` or `local` |
| `logout.redirect_url` | `/` | Post-logout redirect (resolved to absolute URL) |
| `logout.id_token_hint` | `true` | Send id_token for silent logout |
| `logout.backchannel_enabled` | `true` | Enable backchannel logout endpoint |

## Single Sign-On Setup

### Login once, access every app

Apps that share the same Keycloak realm share one Keycloak SSO session. When a
user logs in at App A, Keycloak sets an auth cookie; when they visit App B, that
app redirects to Keycloak, the cookie authenticator recognises the session, and
the user is signed in **without a login page or clicking Google again**.

The part that trips people up: **each app still has its own Laravel session**,
and SSO only kicks in once the app actually asks Keycloak who the visitor is.
Until something triggers that authorization request, App B sees a plain guest —
even though the Keycloak session is right there. Something has to start the
conversation:

| Trigger | Effect |
|---------|--------|
| The user clicks a "Login" link pointing at `login.keycloak` | Signed in with no Keycloak/Google screen |
| `routes.auto_login_redirect` = `true` | Guests hitting an `auth` route are signed in automatically |
| `silent_sso.enabled` = `true` | Guests are signed in automatically on **any** page, including public ones |

On the Laravel side, keep the SSO-friendly defaults:

- `KEYCLOAK_REMEMBER_LOGIN=false` — let Keycloak own the session (see below)
- Every app points at the same realm (each app gets its own client)

### Silent SSO check (`prompt=none`)

```env
KEYCLOAK_SILENT_SSO=true
```

That is the whole setup. Guests are then sent once through
`/auth/keycloak/silent-check`, which asks Keycloak for an authorization code
with `prompt=none`. OIDC guarantees no UI for that request, so one of two things
happens:

- **An SSO session exists** → Keycloak returns a code, the normal callback runs,
  and the user lands back on the page they asked for, logged in.
- **No SSO session** → Keycloak answers `error=login_required`, and the user is
  returned to the same page as a guest. No error page, no flash message.

Either way the user only sees their own page. Behind the scenes:

- The check runs *before* the `auth` middleware, so a guest opening a protected
  page is signed in silently instead of being bounced to the login page first.
- Logging out stamps the check, so the page a user lands on after logout does
  not sign them straight back in — including in `logout.mode` = `local`, where
  the Keycloak SSO session deliberately stays alive.
- The attempt is stamped in the session *before* leaving the app, so a broken
  round-trip can never cause a redirect loop.
- A guest is re-checked at most once every `silent_sso.retry_after` seconds
  (default 600; set `0` to check only once per session). Lower it if users
  should pick up a login from another app faster, at the cost of more redirects.
- Only plain GET page loads are interrupted — never POSTs, XHR/JSON requests, or
  the package's own auth routes. When `routes.auto_login_redirect` is on, the
  `login` route is skipped too: it already forwards to Keycloak interactively.
- `kc_idp_hint` is deliberately **not** sent on the silent check: with
  `prompt=none` no interaction is allowed, so hinting at an IdP is meaningless.

To pick the routes yourself instead of covering the whole `web` group, disable
auto-apply and use the `keycloak.sso` middleware alias:

```env
KEYCLOAK_SILENT_SSO=true
KEYCLOAK_SILENT_SSO_AUTO_APPLY=false
```

```php
Route::middleware(['web', 'keycloak.sso'])->group(function () {
    Route::get('/', HomeController::class);
});
```

### Skip the Keycloak login page (go straight to Google)

By default Keycloak shows its own login page with a "Google" button. To send
users **directly to Google** — no Keycloak page, no button click — enable one
of the two options below (both do the same thing):

**Option A — app-side (`KEYCLOAK_IDP_HINT`)**

The package adds `kc_idp_hint=google` to every authorization URL:

```env
KEYCLOAK_IDP_HINT=google
```

**Option B — Keycloak-side (`Authenticate by default`)**

**Realm → Identity Providers → google → turn ON "Authenticate by default"**

or equivalently: **Realm → Authentication → Browser flow → Identity Provider
Redirector → "Default Identity Provider" = google**

> If **neither** option is set, Keycloak shows its own login page. If both are
> set, the per-request `kc_idp_hint` wins.

### Keycloak browser flow order

The default flow already favours SSO — keep it in this order:

1. **Cookie** — checks the SSO cookie first, so returning users sign in
   instantly (this is what makes cross-app SSO work).
2. **Identity Provider Redirector** — with Google set as the default IdP, new
   users are sent straight to Google.

If users still land on the Keycloak login page, check that (a) Google is the
default IdP and (b) the Cookie authenticator is the first step in the browser
flow.

## Important Notes

### Why `remember_login` should be `false` for SSO

In an SSO ecosystem, session management should be handled by Keycloak — not by Laravel's remember cookie. If `remember_login` is `true`:
- User can bypass Keycloak callback on subsequent visits (via cookie)
- `id_token` won't be stored in session → logout requires confirmation page
- Backchannel logout can't fully work (user re-authenticates via cookie)

### Google/IDP Session Behavior

If users log in via an external IDP (Google, GitHub, etc.) through Keycloak,
logging out from Keycloak does **not** log them out from the IDP. Google in
particular implements no OIDC logout at all — its discovery document carries no
`end_session_endpoint`, no `frontchannel_logout_supported` and no
`backchannel_logout_supported`; the only related endpoint revokes tokens, not the
browser session. So there is no supported way to federate a logout to Google, and
`accounts.google.com/Logout` is not one either: it signs the person out of every
Google service in that browser.

The consequence is a logout that looks broken but isn't. The app's session is
gone and Keycloak's SSO session is gone, but the moment the user touches a page
that forces a login, Keycloak asks Google, Google recognises its own still-valid
session, and hands the user straight back — signed in, in a fraction of a second.

Two things fix the experience, and they compose:

**1. Keep one page reachable without login.** Land the user there after logout
(`logout.redirect_url`). Nothing forces a login, so the silent check answers
`login_required` and they come to rest logged out. Without such a page every
route re-authenticates them immediately.

**2. Set the IDP's `prompt` so the next login is deliberate.** In Keycloak:
**Identity providers → google → Advanced settings → Prompt**.

| Value | Sent to Google | What the user gets |
|-------|----------------|--------------------|
| *(empty)* | no `prompt` parameter | Default. After logout the next login is silent — they are simply back in. |
| **`select_account`** | `prompt=select_account` | **Recommended.** Google shows the account chooser. Logout feels real, and switching accounts becomes possible. Costs one click per fresh login. |
| `consent` | `prompt=consent` | Re-displays the scope consent screen on every login. Excessive for daily use. |
| `login` | `prompt=login` | The OIDC way to force re-authentication, but not every IDP honours it — don't rely on it with Google. |
| `none` | `prompt=none` | **Not here.** It forbids all interaction at the IDP, so a user without a Google session fails with an error instead of being asked to log in. |

**This does not weaken cross-app SSO.** The two `prompt` values live on different
hops and never meet:

| Hop | Sender | Value | When |
|-----|--------|-------|------|
| App → Keycloak | this package's silent check | `prompt=none` | Every guest, once per session |
| Keycloak → Google | the IDP setting above | `select_account` | Only when Keycloak actually has to ask Google |

While a Keycloak SSO session exists, the silent check is answered from Keycloak's
own cookie and **never reaches Google** — so "log in once, land signed in
everywhere" stays as silent as before. The IDP prompt is felt only when Keycloak
genuinely must re-authenticate: right after a logout, or once the SSO session has
expired.

## Troubleshooting

### "I logged in at App A, but App B still shows me as a guest"

Expected, until App B asks Keycloak who the visitor is. The Keycloak SSO
session is shared; the Laravel session is not — each app has its own session
cookie on its own domain. Nothing carries over until App B sends an
authorization request. Pick a trigger: a login link to `login.keycloak`,
`routes.auto_login_redirect` for protected pages, or `silent_sso.enabled` for
every page (see [Single Sign-On Setup](#single-sign-on-setup)).

### The silent check redirects but the user is never signed in

Look at what Keycloak returns to `/auth/keycloak/callback`:

- `error=login_required` — there is genuinely no SSO session. Confirm both apps
  point at the **same realm** and the same `KEYCLOAK_BASE_URL` host, so they
  share the SSO cookie. Different hosts for the same Keycloak (`localhost` vs
  `127.0.0.1`, say) mean different cookies and therefore different sessions.
- `error=invalid_client` / `invalid_redirect_uri` — this app's client id,
  secret, or redirect URI is wrong for the realm.

### Users bounce between the app and Keycloak

Each silent check is stamped in the session before leaving the app, so the
middleware cannot loop on its own. A loop usually means the session is not
persisting: check `SESSION_DOMAIN`, and that apps on different domains are not
sharing a cookie name with a domain-wide `SESSION_DOMAIN`.

### Logging out works, but a refresh signs the user straight back in

Not a logout failure. The Keycloak session really is gone — check the other app's
log for `destroyed sessions` — but the IDP session is not, and the page the user
refreshed forces a login. See
[Google/IDP Session Behavior](#googleidp-session-behavior): keep a page reachable
without login, and set the IDP's `prompt` to `select_account` if logout needs to
feel final.

### Logging out of one app leaves the others logged in

That is backchannel logout, not the silent SSO check. Work through
[Verifying it works](#verifying-it-works): the usual causes are front-channel
logout still being ON for the client, or Keycloak not being able to reach your
app's hostname from inside its own container.

### Nothing happens at all

`silent_sso.enabled` must be `true` *and* the middleware must be applied. With
`auto_apply` on, confirm it landed in the group:

```bash
php artisan route:list -v --path=/
```

If you set `auto_apply` to `false`, apply `keycloak.sso` to your routes
yourself.

## Testing

```bash
composer test
```

## License

MIT — see [LICENSE.md](LICENSE.md).

## Credits

- [Toni Listiyo](https://github.com/toniel)
