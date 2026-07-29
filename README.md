# Laravel Keycloak Socialite

Reusable Keycloak Socialite authentication for Laravel applications. Provides a drop-in Keycloak SSO integration with redirect, callback, logout, and **backchannel logout** routes — configurable and decoupled from any specific User model or permission system.

## Features

- **SSO Login** — Redirect to Keycloak, callback with auto-register
- **Silent Logout** — Logout from Keycloak without confirmation page (via `id_token_hint`)
- **Backchannel Logout** — When user logs out from any app, all other apps in the ecosystem are logged out automatically
- **Cross-app SSO** — Login once, access all apps in the same Keycloak realm
- **Configurable IDP Hint** — Skip Keycloak login screen, go directly to Google/GitHub/etc
- **Event-driven** — Hook into login, registration, and failure events

## Requirements

- PHP 8.2+
- Laravel 12+ / 13+
- A running [Keycloak](https://www.keycloak.org/) server (tested with v26+)

## Installation

```bash
composer require toniel/laravel-keycloak-socialite
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
KEYCLOAK_IDP_HINT=google              # Skip Keycloak login screen, go directly to an IDP
KEYCLOAK_AUTO_REGISTER=true           # Create users automatically on first login
KEYCLOAK_REMEMBER_LOGIN=false         # Disable for SSO — let Keycloak manage session
KEYCLOAK_REDIRECT_URL=/dashboard      # Fallback post-login redirect

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
| POST | `/auth/keycloak/backchannel-logout` | `login.keycloak.backchannel-logout` | Receive logout signal from Keycloak |

To disable auto-registration and define your own routes, set `routes.enabled` to `false` in config.

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
4. Turn ON **"Backchannel logout session required"**

> **Note:** If Keycloak runs in Docker, it must be able to reach your app's URL. Add `extra_hosts` to your docker-compose:
> ```yaml
> services:
>   keycloak:
>     extra_hosts:
>       - "your-app.test:host-gateway"
> ```

**Supported session drivers:** `database` (recommended). For `file` driver, the package invalidates the remember token as fallback.

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
| `logout.mode` | `keycloak` | `keycloak` or `local` |
| `logout.redirect_url` | `/` | Post-logout redirect (resolved to absolute URL) |
| `logout.id_token_hint` | `true` | Send id_token for silent logout |
| `logout.backchannel_enabled` | `true` | Enable backchannel logout endpoint |

## Important Notes

### Why `remember_login` should be `false` for SSO

In an SSO ecosystem, session management should be handled by Keycloak — not by Laravel's remember cookie. If `remember_login` is `true`:
- User can bypass Keycloak callback on subsequent visits (via cookie)
- `id_token` won't be stored in session → logout requires confirmation page
- Backchannel logout can't fully work (user re-authenticates via cookie)

### Google/IDP Session Behavior

If users log in via an external IDP (Google, GitHub, etc.) through Keycloak, logging out from Keycloak does **not** log them out from the IDP. If the IDP session is still active, Keycloak may silently re-authenticate the user on next access. This is expected SSO behavior.

## Testing

```bash
composer test
```

## License

MIT — see [LICENSE.md](LICENSE.md).

## Credits

- [Toni Listiyo](https://github.com/toniel)
