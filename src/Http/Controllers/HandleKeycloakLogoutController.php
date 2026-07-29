<?php

namespace Toniel\LaravelKeycloakSocialite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HandleKeycloakLogoutController
{
    /**
     * Log the user out from both Laravel and Keycloak.
     *
     * If id_token is available in session, sends id_token_hint for silent
     * logout (Keycloak processes immediately without confirmation page).
     *
     * If id_token is NOT available (e.g. user was re-authenticated via
     * remember cookie), falls back to Keycloak logout with client_id only.
     * In this case Keycloak MAY show a confirmation page unless the client
     * is configured to not require it.
     */
    public function __invoke(): RedirectResponse
    {
        $mode = config('keycloak-socialite.logout.mode', 'keycloak');
        $kc = config('keycloak-socialite.keycloak');

        // Resolve the post-logout redirect to an absolute URL
        $redirectAfter = config('keycloak-socialite.logout.redirect_url', '/');
        if (! str_starts_with($redirectAfter, 'http')) {
            $redirectAfter = rtrim(config('app.url'), '/') . '/' . ltrim($redirectAfter, '/');
        }

        // ── Build Keycloak logout URL BEFORE destroying session ──
        $logoutUrl = null;
        if ($mode === 'keycloak') {
            $idToken = null;
            if (config('keycloak-socialite.logout.id_token_hint', true)) {
                $idToken = session('keycloak_id_token');
            }

            $baseUrl = rtrim($kc['base_url'], '/');
            $realm = $kc['realms'] ?? 'master';

            $params = [
                'post_logout_redirect_uri' => $redirectAfter,
                'client_id' => $kc['client_id'],
            ];

            if ($idToken) {
                $params['id_token_hint'] = $idToken;
            }

            $logoutUrl = "{$baseUrl}/realms/{$realm}/protocol/openid-connect/logout?"
                . http_build_query($params);
        }

        // ── Destroy local session ──
        $user = Auth::user();

        // Invalidate remember token so the browser cookie becomes useless
        if ($user && method_exists($user, 'setRememberToken')) {
            $user->setRememberToken('');
            $user->save();
        }

        Auth::logout();

        if (session()->isStarted()) {
            session()->invalidate();
            session()->regenerateToken();
        }

        // ── Redirect ──
        if ($logoutUrl) {
            return redirect()->away($logoutUrl);
        }

        return redirect()->to($redirectAfter);
    }
}
