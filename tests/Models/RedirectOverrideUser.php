<?php

namespace Toniel\LaravelKeycloakSocialite\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Toniel\LaravelKeycloakSocialite\Contracts\KeycloakAuthenticatable;
use Toniel\LaravelKeycloakSocialite\Traits\HasKeycloakIdentity;

class RedirectOverrideUser extends Authenticatable implements KeycloakAuthenticatable
{
    use HasKeycloakIdentity;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'keycloak_id', 'keycloak_avatar'];

    protected $guarded = [];

    public function getKeycloakRedirectUrl(): ?string
    {
        return '/custom-dashboard';
    }
}
