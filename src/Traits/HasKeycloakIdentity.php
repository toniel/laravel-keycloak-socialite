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
     * Update keycloak_id, keycloak_avatar, and optional federated identity data.
     * Only updates fields that are currently null (first-login wins behavior).
     */
    public function updateKeycloakIdentity(string $keycloakId, ?string $avatar, array $additionalData = []): bool
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

        // Optional: Update Google identity if provided and not already set
        if (isset($additionalData['google_id']) && is_null($this->google_id)) {
            $update['google_id'] = $additionalData['google_id'];
        }

        if (isset($additionalData['google_avatar']) && is_null($this->google_avatar)) {
            $update['google_avatar'] = $additionalData['google_avatar'];
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

        $fillable = [
            'name'          => $socialiteUser->getName(),
            'email'         => $socialiteUser->getEmail(),
            $idColumn       => $socialiteUser->getId(),
            $avatarColumn   => $socialiteUser->getAvatar(),
            'password'      => Hash::make(Str::random(24)),
        ];

        // Optional: Include Google identity if passed from Keycloak via custom claims
        // This will only work if Keycloak is configured with Identity Provider Mappers
        $rawUser = $socialiteUser->getRaw();

        if (isset($rawUser['google_id'])) {
            $fillable['google_id'] = $rawUser['google_id'];
        }

        if (isset($rawUser['google_avatar'])) {
            $fillable['google_avatar'] = $rawUser['google_avatar'];
        }

        return $fillable;
    }
}
