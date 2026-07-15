# Laravel Keycloak Socialite

Reusable Keycloak Socialite authentication for Laravel applications. Provides a drop-in Keycloak SSO integration with redirect, callback, and logout routes — configurable and decoupled from any specific User model or permission system.

## Requirements

- PHP 8.2+
- Laravel 12+
- A running [Keycloak](https://www.keycloak.org/) server

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
KEYCLOAK_IDP_HINT=google          # Skip Keycloak's login screen, go directly to an IDP
KEYCLOAK_AUTO_REGISTER=true       # Create users automatically on first login
KEYCLOAK_REMEMBER_LOGIN=true      # "Remember me" when logging in
KEYCLOAK_REDIRECT_URL=/dashboard  # Fallback post-login redirect
```

## Setup Your User Model

Your User model must implement `Toniel\LaravelKeycloakSocialite\Contracts\KeycloakAuthenticatable`. You can use the included trait for the default implementation:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Toniel\LaravelKeycloakSocialite\Contracts\KeycloakAuthenticatable;
use Toniel\LaravelKeycloakSocialite\Traits\HasKeycloakIdentity;

class User extends Authenticatable implements KeycloakAuthenticatable
{
    use HasKeycloakIdentity;

    /**
     * Override to provide app-specific redirect logic.
     * Return null to use the config fallback.
     */
    public function getKeycloakRedirectUrl(): ?string
    {
        // Example with role-based dashboards:
        return match (true) {
            $this->hasRole('admin') => '/admin/dashboard',
            $this->hasRole('student') => '/student/dashboard',
            default => '/dashboard',
        };
    }
}
```

### Without the Trait

Implement all four methods yourself:

```php
use Toniel\LaravelKeycloakSocialite\Contracts\KeycloakAuthenticatable;

class User extends Authenticatable implements KeycloakAuthenticatable
{
    public static function findByKeycloakEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }

    public static function createFromKeycloak(array $attributes): self
    {
        return static::create($attributes);
    }

    public function updateKeycloakIdentity(string $keycloakId, ?string $avatar): bool
    {
        return $this->update([
            'keycloak_id' => $keycloakId,
            'keycloak_avatar' => $avatar,
        ]);
    }

    public function getKeycloakRedirectUrl(): ?string
    {
        return '/dashboard';
    }
}
```

## Routes

The package automatically registers three routes. You can change the URIs and names in the config file.

| Method | Default URI | Named Route |
|--------|-------------|-------------|
| GET | `/auth/keycloak` | `login.keycloak` |
| GET | `/auth/keycloak/callback` | `login.keycloak.callback` |
| GET | `/auth/keycloak/logout` | `logout.keycloak` |

To disable auto-registration and define your own routes, set `routes.enabled` to `false` in `config/keycloak-socialite.php`.

## Events

The package fires events you can listen to for custom behaviour:

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

Fired when a new user is created from Keycloak data. Use this to assign default roles, send welcome emails, or set up profile defaults.

```php
use Toniel\LaravelKeycloakSocialite\Events\KeycloakUserCreated;

Event::listen(KeycloakUserCreated::class, function (KeycloakUserCreated $event) {
    // Assign a default role
    $event->user->assignRole('student');
});
```

### `KeycloakAuthenticationFailed`

Fired when authentication fails (Socialite exception, or unknown user with `auto_register` disabled).

```php
use Toniel\LaravelKeycloakSocialite\Events\KeycloakAuthenticationFailed;

Event::listen(KeycloakAuthenticationFailed::class, function (KeycloakAuthenticationFailed $event) {
    // $event->reason        — description of the failure
    // $event->exception     — the Throwable, if any

    // Override where to redirect on failure:
    $event->errorRedirect = '/custom-error-page';
});
```

## Customising the Controller

If you need to override the controller logic entirely, bind your own controller in a service provider, then update the config:

```php
// In your AppServiceProvider
$this->app->bind(
    \Toniel\LaravelKeycloakSocialite\Contracts\KeycloakAuthenticatable::class,
    \App\Models\User::class
);
```

Then in `config/keycloak-socialite.php`, change the `user_model` value:

```php
'user_model' => \App\Models\User::class,
```

## Configuration Reference

See `config/keycloak-socialite.php` for all options. Key settings:

| Key | Default | Description |
|-----|---------|-------------|
| `user_model` | `App\Models\User` | FQCN of your User model |
| `redirect_url` | `/dashboard` | Fallback post-login redirect |
| `idp_hint` | `google` | Keycloak `kc_idp_hint` parameter |
| `auto_register` | `true` | Create users on first login |
| `remember_login` | `true` | "Remember me" on login |
| `routes.enabled` | `true` | Auto-register routes |
| `logout.redirect_after_logout` | `/` | Where to go after Keycloak logout |

## Testing

```bash
composer test
```

## License

MIT — see [LICENSE.md](LICENSE.md).

## Credits

- [Toni Listiyo](https://github.com/toniel)
