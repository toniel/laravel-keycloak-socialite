<?php

namespace Toniel\LaravelKeycloakSocialite\Providers;

use Illuminate\Support\Arr;
use SocialiteProviders\Keycloak\Provider as BaseKeycloakProvider;
use SocialiteProviders\Manager\OAuth2\User;

class ExtendedKeycloakProvider extends BaseKeycloakProvider
{
    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        // Start with base mapping
        $mappedUser = (new User())->setRaw($user)->map([
            'id'        => Arr::get($user, 'sub'),
            'nickname'  => Arr::get($user, 'preferred_username'),
            'name'      => Arr::get($user, 'name'),
            'email'     => Arr::get($user, 'email'),
            'avatar'    => Arr::get($user, 'picture'), // Keycloak avatar if available
        ]);

        // Optional: Extract Google federated identity if Keycloak is configured to pass it
        // These custom claims would come from Keycloak Identity Provider Mappers
        if (Arr::has($user, 'google_id')) {
            $mappedUser->google_id = Arr::get($user, 'google_id');
        }

        if (Arr::has($user, 'google_avatar')) {
            $mappedUser->google_avatar = Arr::get($user, 'google_avatar');
        }

        return $mappedUser;
    }
}
