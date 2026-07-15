<?php

namespace Toniel\LaravelKeycloakSocialite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class HandleKeycloakLogoutController
{
    /**
     * Log the user out of the application and redirect to Keycloak's logout endpoint.
     */
    public function __invoke(): RedirectResponse
    {
        $redirectAfterLogout = config('keycloak-socialite.logout.redirect_after_logout', '/');

        try {
            // Get the Keycloak logout URL from the Socialite provider
            $logoutUrl = Socialite::driver('keycloak')->getLogoutUrl(
                $redirectAfterLogout,
                config('keycloak-socialite.keycloak.client_id'),
            );

            // Log out from the Laravel session
            Auth::logout();

            // Redirect to Keycloak's logout endpoint
            return redirect()->to($logoutUrl);
        } catch (\Exception $e) {
            Log::error('Keycloak logout failed: ' . $e->getMessage());

            // Fallback: just log out locally
            Auth::logout();

            return redirect()->to($redirectAfterLogout)
                ->with('error', 'Failed to log out from Keycloak.');
        }
    }
}
