<?php

namespace Toniel\LaravelKeycloakSocialite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class HandleKeycloakLogoutController
{
    /**
     * Log the user out from both the Laravel session and Keycloak.
     */
    public function __invoke(): RedirectResponse
    {
        $redirectAfterLogout = config('keycloak-socialite.logout.redirect_after_logout', '/');
        $clientId = config('services.keycloak.client_id');

        // Get the Keycloak logout URL from the provider
        $logoutUrl = Socialite::driver('keycloak')
            ->getLogoutUrl($redirectAfterLogout, $clientId);

        // Destroy local session
        Auth::logout();

        if (session()->isStarted()) {
            session()->invalidate();
            session()->regenerateToken();
        }

        return redirect()->away($logoutUrl);
    }
}
