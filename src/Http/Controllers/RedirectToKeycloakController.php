<?php

namespace Toniel\LaravelKeycloakSocialite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class RedirectToKeycloakController
{
    /**
     * Redirect the user to the Keycloak authorization endpoint.
     */
    public function __invoke(): RedirectResponse
    {
        $driver = Socialite::driver('keycloak');

        $idpHint = config('keycloak-socialite.idp_hint');

        if ($idpHint) {
            $driver->with(['kc_idp_hint' => $idpHint]);
        }

        return $driver->redirect();
    }
}
