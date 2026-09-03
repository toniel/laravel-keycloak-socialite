<?php

namespace Toniel\LaravelKeycloakSocialite\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Toniel\LaravelKeycloakSocialite\Contracts\KeycloakAuthenticatable;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakAuthenticationFailed;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakUserAuthenticated;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakUserCreated;
use Toniel\LaravelKeycloakSocialite\Support\SilentSso;

class HandleKeycloakCallbackController
{
    /**
     * Handle the Keycloak callback and authenticate the user.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        // 0. Keycloak answered with an error instead of a code. For a silent
        //    check (prompt=none) "no SSO session" is a normal answer, so the
        //    user is returned to their page as a guest, quietly.
        if ($request->filled('error')) {
            return $this->handleErrorResponse($request);
        }

        $wasSilent = (bool) session()->pull(SilentSso::SESSION_SILENT, false);

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

            // A silent check must never dump the user on an error page —
            // it was triggered by a page view, not by them clicking login.
            if ($wasSilent) {
                $returnTo = SilentSso::returnTo();
                SilentSso::forgetAttempt();

                return redirect()->to($returnTo);
            }

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

            SilentSso::forgetAttempt();

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

        SilentSso::forgetAttempt();

        return redirect()->intended($redirectUrl);
    }

    /**
     * Handle an `error=...` response from the Keycloak authorization endpoint.
     */
    protected function handleErrorResponse(Request $request): RedirectResponse
    {
        $error = (string) $request->query('error');
        $wasSilent = (bool) session()->pull(SilentSso::SESSION_SILENT, false);

        // Don't retry straight away, whatever the outcome.
        SilentSso::stampAttempt();

        if ($wasSilent && in_array($error, SilentSso::NO_SESSION_ERRORS, true)) {
            // Expected: there is simply no Keycloak SSO session yet.
            $returnTo = SilentSso::returnTo();
            SilentSso::forgetAttempt();

            return redirect()->to($returnTo);
        }

        $description = (string) $request->query('error_description', '');
        $reason = trim($error . ' ' . $description);

        Log::warning('Keycloak authorization error: ' . $reason);

        $errorRedirect = route('login');
        $errorMessage = 'Failed to authenticate with Keycloak.';

        event(new KeycloakAuthenticationFailed(
            reason: $reason,
            errorRedirect: $errorRedirect,
            errorMessage: $errorMessage,
        ));

        if ($wasSilent) {
            $returnTo = SilentSso::returnTo();
            SilentSso::forgetAttempt();

            return redirect()->to($returnTo);
        }

        SilentSso::forgetAttempt();

        return redirect()->to($errorRedirect)->with('error', $errorMessage);
    }
}
