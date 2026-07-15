<?php

namespace Toniel\LaravelKeycloakSocialite\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class KeycloakUserAuthenticated
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  \Laravel\Socialite\Contracts\User  $socialiteUser
     * @param  string|null  $redirectUrl  Passed by reference — listeners may override it.
     */
    public function __construct(
        public Authenticatable $user,
        public SocialiteUser $socialiteUser,
        public ?string &$redirectUrl = null,
    ) {}
}
