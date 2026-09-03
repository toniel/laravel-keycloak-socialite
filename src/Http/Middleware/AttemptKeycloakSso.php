<?php

namespace Toniel\LaravelKeycloakSocialite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Toniel\LaravelKeycloakSocialite\Support\SilentSso;

/**
 * Sends guests through a one-off `prompt=none` check so an existing Keycloak
 * SSO session logs them in without any visible login step.
 */
class AttemptKeycloakSso
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldAttempt($request)) {
            return $next($request);
        }

        SilentSso::rememberReturnTo($request);

        return redirect()->route(
            config('keycloak-socialite.silent_sso.route_as', 'login.keycloak.silent-check')
        );
    }

    /**
     * Only plain, guest, top-level page loads are worth interrupting.
     */
    protected function shouldAttempt(Request $request): bool
    {
        if (! SilentSso::enabled() || Auth::check()) {
            return false;
        }

        // A redirect is only meaningful for a document request the browser
        // can follow: no POSTs, no XHR/JSON, no prefetch.
        if (! $request->isMethod('GET') || $request->ajax() || $request->expectsJson()) {
            return false;
        }

        if ($this->isExcluded($request)) {
            return false;
        }

        return ! SilentSso::checkedRecently();
    }

    /**
     * The package's own auth endpoints must never be intercepted, or the
     * check would redirect to itself.
     */
    protected function isExcluded(Request $request): bool
    {
        $routes = config('keycloak-socialite.routes', []);

        $paths = [
            $routes['redirect'] ?? 'auth/keycloak',
            $routes['callback'] ?? 'auth/keycloak/callback',
            $routes['logout'] ?? 'auth/keycloak/logout',
            $routes['backchannel_logout'] ?? 'auth/keycloak/backchannel-logout',
            config('keycloak-socialite.silent_sso.route', 'auth/keycloak/silent-check'),
        ];

        // With auto_login_redirect on, the login route already forwards to
        // Keycloak interactively — checking silently first is a wasted trip.
        if (config('keycloak-socialite.routes.auto_login_redirect', false)) {
            $paths[] = $routes['login'] ?? 'login';
        }

        $paths = array_filter([
            ...$paths,
            ...(array) config('keycloak-socialite.silent_sso.except', []),
        ]);

        foreach ($paths as $path) {
            if ($request->is(ltrim($path, '/'))) {
                return true;
            }
        }

        return false;
    }
}
