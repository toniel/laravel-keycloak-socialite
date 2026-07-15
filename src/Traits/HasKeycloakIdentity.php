<?php

namespace Toniel\LaravelKeycloakSocialite\Traits;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

trait HasKeycloakIdentity
{
    /**
     * Find a user by their email address.
     */
    public static function findByKeycloakEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }

    /**
     * Create a new user from Keycloak attributes.
     */
    public static function createFromKeycloak(array $attributes): self
    {
        return static::create($attributes);
    }

    /**
     * Update keycloak_id and keycloak_avatar — only when they are not already set.
     *
     * This matches the "first-login wins" behaviour: once a user logs in via
     * Keycloak, subsequent logins do not overwrite these fields.
     */
    public function updateKeycloakIdentity(string $keycloakId, ?string $avatar): bool
    {
        $idColumn = config('keycloak-socialite.columns.keycloak_id', 'keycloak_id');
        $avatarColumn = config('keycloak-socialite.columns.keycloak_avatar', 'keycloak_avatar');

        $update = [];

        if (is_null($this->{$idColumn})) {
            $update[$idColumn] = $keycloakId;
        }

        if (is_null($this->{$avatarColumn}) && $avatar) {
            $update[$avatarColumn] = $avatar;
        }

        if (! empty($update)) {
            return $this->update($update);
        }

        return true;
    }

    /**
     * Return the URL to redirect to after login.
     *
     * Override this in your User model to provide app-specific logic
     * (e.g. role-based dashboards). Return null to use the config fallback.
     */
    public function getKeycloakRedirectUrl(): ?string
    {
        return config('keycloak-socialite.redirect_url', '/dashboard');
    }

    /**
     * Build the fillable attributes array from a Socialite user.
     *
     * This helper is used by the controller to prepare data for createFromKeycloak().
     * Column names are read from config so they work regardless of the actual
     * database columns.
     */
    public static function keycloakFillableFromSocialite(SocialiteUser $socialiteUser): array
    {
        $idColumn = config('keycloak-socialite.columns.keycloak_id', 'keycloak_id');
        $avatarColumn = config('keycloak-socialite.columns.keycloak_avatar', 'keycloak_avatar');

        return [
            'name'          => $socialiteUser->getName(),
            'email'         => $socialiteUser->getEmail(),
            $idColumn       => $socialiteUser->getId(),
            $avatarColumn   => $socialiteUser->getAvatar(),
            'password'      => Hash::make(Str::random(24)),
        ];
    }
}
