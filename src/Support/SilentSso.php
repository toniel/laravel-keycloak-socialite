<?php

namespace Toniel\LaravelKeycloakSocialite\Support;

use Illuminate\Http\Request;

/**
 * Shared state for the silent SSO check (prompt=none).
 *
 * Keeps the session keys and the "have we checked recently?" rule in one
 * place, since the middleware, the check controller and the callback
 * controller all need to agree on them.
 */
class SilentSso
{
    /** Marks the in-flight authorization request as a silent check. */
    public const SESSION_SILENT = 'keycloak_sso_silent';

    /** Unix timestamp of the last silent check attempt. */
    public const SESSION_CHECKED_AT = 'keycloak_sso_checked_at';

    /** Where to send the user back to once the check resolves. */
    public const SESSION_RETURN_TO = 'keycloak_sso_return_to';

    /**
     * OIDC error codes that simply mean "no SSO session, nothing to do".
     * These are expected outcomes of prompt=none, not failures.
     */
    public const NO_SESSION_ERRORS = [
        'login_required',
        'interaction_required',
        'consent_required',
        'account_selection_required',
    ];

    /**
     * Is the silent check enabled?
     */
    public static function enabled(): bool
    {
        return (bool) config('keycloak-socialite.silent_sso.enabled', false);
    }

    /**
     * Record that a check was just attempted.
     */
    public static function stampAttempt(): void
    {
        session()->put(self::SESSION_CHECKED_AT, now()->getTimestamp());
    }

    /**
     * Has a check already been attempted recently enough to skip a new one?
     *
     * `retry_after` is in seconds; 0 (or less) means check only once per
     * session.
     */
    public static function checkedRecently(): bool
    {
        $checkedAt = session(self::SESSION_CHECKED_AT);

        if ($checkedAt === null) {
            return false;
        }

        $retryAfter = (int) config('keycloak-socialite.silent_sso.retry_after', 600);

        if ($retryAfter <= 0) {
            return true;
        }

        return (now()->getTimestamp() - (int) $checkedAt) < $retryAfter;
    }

    /**
     * Remember the page the user was on when the check started.
     */
    public static function rememberReturnTo(Request $request): void
    {
        session()->put(self::SESSION_RETURN_TO, $request->fullUrl());
    }

    /**
     * The page to return to, falling back to the app root.
     */
    public static function returnTo(): string
    {
        return session(self::SESSION_RETURN_TO) ?: url('/');
    }

    /**
     * Clear the per-attempt state (the timestamp is deliberately kept).
     */
    public static function forgetAttempt(): void
    {
        session()->forget([self::SESSION_SILENT, self::SESSION_RETURN_TO]);
    }
}
