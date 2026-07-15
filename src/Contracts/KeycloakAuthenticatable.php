<?php

namespace Toniel\LaravelKeycloakSocialite\Contracts;

interface KeycloakAuthenticatable
{
    /**
     * Find an existing user by the email address returned from Keycloak.
     *
     * @param  string  $email  The email address from the Keycloak userinfo endpoint.
     * @return static|null
     */
    public static function findByKeycloakEmail(string $email): ?self;

    /**
     * Create a new user from Keycloak Socialite user attributes.
     *
     * Called when no existing user is found by email and auto_register is enabled.
     *
     * @param  array  $attributes  Validated key-value pairs ready for mass-assignment
     *                             (typically: email, name, keycloak_id, keycloak_avatar, password).
     * @return static
     */
    public static function createFromKeycloak(array $attributes): self;

    /**
     * Update the user's Keycloak identity fields.
     *
     * Called on every successful authentication for existing users.
     * The implementation decides whether to always overwrite or only fill unset fields.
     *
     * @param  string       $keycloakId
     * @param  string|null  $avatar
     * @return bool  Whether the update succeeded.
     */
    public function updateKeycloakIdentity(string $keycloakId, ?string $avatar): bool;

    /**
     * Return the URL the user should be redirected to after a successful
     * Keycloak login.
     *
     * Return null to fall back to the config default (keycloak-socialite.redirect_url).
     *
     * @return string|null
     */
    public function getKeycloakRedirectUrl(): ?string;
}
