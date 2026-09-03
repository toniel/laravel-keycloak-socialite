<?php

namespace Toniel\LaravelKeycloakSocialite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Toniel\LaravelKeycloakSocialite\Support\SilentSso;

class SilentSsoCheckController
{
    /**
     * Ask Keycloak whether an SSO session already exists, without ever
     * showing UI to the user.
     *
     * `prompt=none` tells Keycloak: authenticate from the SSO cookie or
     * fail immediately with `error=login_required`. Either way the user
     * never sees a login page, so this is safe to trigger on page loads.
     */
    public function __invoke(): RedirectResponse
    {
        // Stamp the attempt *before* leaving the app. If anything goes wrong
        // downstream and we never reach the callback, the middleware still
        // sees a recent attempt and won't bounce the user again.
        SilentSso::stampAttempt();

        $returnTo = SilentSso::returnTo();

        if (Auth::check()) {
            return redirect()->to($returnTo);
        }

        // Only set the intended URL when nothing else claimed it — the `auth`
        // middleware may already have stored the page the user asked for.
        if (! session()->has('url.intended')) {
            redirect()->setIntendedUrl($returnTo);
        }

        session()->put(SilentSso::SESSION_SILENT, true);

        // No kc_idp_hint here: with prompt=none no interaction is allowed,
        // so hinting at an IdP would be meaningless.
        return Socialite::driver('keycloak')
            ->with(['prompt' => 'none'])
            ->redirect();
    }
}
