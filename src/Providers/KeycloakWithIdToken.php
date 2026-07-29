<?php

namespace Toniel\LaravelKeycloakSocialite\Providers;

use SocialiteProviders\Keycloak\Provider as KeycloakProvider;

class KeycloakWithIdToken extends KeycloakProvider
{
    /**
     * Override to capture the id_token from the token response
     * and store it in the raw user data.
     *
     * Without this, the id_token is lost because Socialite's
     * AbstractUser doesn't expose it.
     */
    protected function userInstance(array $response, array $user)
    {
        $instance = parent::userInstance($response, $user);

        if (isset($response['id_token'])) {
            $instance->setRaw(array_merge($instance->getRaw(), [
                'id_token' => $response['id_token'],
            ]));
        }

        return $instance;
    }
}
