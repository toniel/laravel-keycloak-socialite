<?php

namespace Toniel\LaravelKeycloakSocialite\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Toniel\LaravelKeycloakSocialite\Contracts\KeycloakAuthenticatable;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakAuthenticationFailed;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakUserAuthenticated;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakUserCreated;

class HandleKeycloakCallbackController
{
    /**
     * Handle the Keycloak callback and authenticate the user.
     */
    public function __invoke(): RedirectResponse
    {
        // 1. Obtain the Socialite user from Keycloak
        try {
            $socialiteUser = Socialite::driver('keycloak')->user();
        } catch (\Exception $e) {
            Log::error('Keycloak authentication failed: ' . $e->getMessage());

            $errorRedirect = route('login');
            $errorMessage = 'Failed to authenticate with Keycloak.';

            event(new KeycloakAuthenticationFailed(
                reason: $e->getMessage(),
                exception: $e,
                errorRedirect: $errorRedirect,
                errorMessage: $errorMessage,
            ));

            return redirect()->to($errorRedirect)
                ->with('error', $errorMessage);
        }

        // 2. Find or create the user
        /** @var class-string<KeycloakAuthenticatable> $userModelClass */
        $userModelClass = config('keycloak-socialite.user_model');

        /** @var KeycloakAuthenticatable|null $user */
        $user = $userModelClass::findByKeycloakEmail($socialiteUser->getEmail());

        $redirectUrl = null; // passed by reference into events

        if ($user) {
            // Existing user — update identity fields
            $user->updateKeycloakIdentity(
                $socialiteUser->getId(),
                $socialiteUser->getAvatar(),
            );

            event(new KeycloakUserAuthenticated(
                user: $user,
                socialiteUser: $socialiteUser,
                redirectUrl: $redirectUrl,
            ));
        } elseif (config('keycloak-socialite.auto_register', true)) {
            // Auto-register new user
            $fillable = $userModelClass::keycloakFillableFromSocialite($socialiteUser);
            $user = $userModelClass::createFromKeycloak($fillable);

            event(new KeycloakUserCreated(
                user: $user,
                socialiteUser: $socialiteUser,
                redirectUrl: $redirectUrl,
            ));
        } else {
            // Auto-register disabled — reject unknown users
            event(new KeycloakAuthenticationFailed(
                reason: 'No user found for email: ' . $socialiteUser->getEmail(),
            ));

            return redirect()->route('login')
                ->with('error', 'No account found for this email address.');
        }

        // 3. Log the user in
        Auth::login($user, config('keycloak-socialite.remember_login', true));

        // 4. Save id_token for silent logout.
        //    SocialiteProviders\Manager stores the full token response in
        //    accessTokenResponseBody (includes id_token from Keycloak).
        $idToken = $socialiteUser->accessTokenResponseBody['id_token'] ?? null;
        if (! $idToken) {
            // Fallback: check getRaw() (for custom providers that put it there)
            $raw = $socialiteUser->getRaw();
            $idToken = $raw['id_token'] ?? null;
        }

        if ($idToken) {
            session()->put('keycloak_id_token', $idToken);
        }

        // 5. Determine redirect URL (priority: event override → user method → config fallback)
        $redirectUrl ??= $user->getKeycloakRedirectUrl();
        $redirectUrl ??= config('keycloak-socialite.redirect_url', '/dashboard');

        return redirect()->intended($redirectUrl);
    }
}
