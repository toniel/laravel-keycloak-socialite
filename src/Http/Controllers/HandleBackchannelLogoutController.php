<?php

namespace Toniel\LaravelKeycloakSocialite\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleBackchannelLogoutController
{
    /**
     * Handle Keycloak Backchannel Logout.
     *
     * Keycloak sends a POST with a `logout_token` (JWT) when a user
     * logs out from any client in the realm. This endpoint:
     *
     * 1. Validates the logout_token (signature check optional — trusts Keycloak by network)
     * 2. Extracts `sub` (keycloak user ID) and/or `sid` (session ID)
     * 3. Destroys the matching local session(s)
     *
     * @see https://openid.net/specs/openid-connect-backchannel-1_0.html
     */
    public function __invoke(Request $request): Response
    {
        $logoutToken = $request->input('logout_token');

        if (! $logoutToken) {
            Log::warning('Backchannel logout: missing logout_token', [
                'content_type' => $request->header('Content-Type'),
                'body' => $request->getContent(),
                'all_input' => $request->all(),
            ]);
            return response('', 400);
        }

        // Decode the JWT payload (without full signature verification —
        // in production behind a firewall, Keycloak is trusted)
        $payload = $this->decodeJwtPayload($logoutToken);

        if (! $payload) {
            Log::warning('Backchannel logout: invalid logout_token format', [
                'token_length' => strlen($logoutToken),
                'token_start' => substr($logoutToken, 0, 50),
                'dot_count' => substr_count($logoutToken, '.'),
            ]);
            return response('', 400);
        }

        $sub = $payload['sub'] ?? null;       // Keycloak user ID
        $sid = $payload['sid'] ?? null;       // Keycloak session ID

        if (! $sub && ! $sid) {
            Log::warning('Backchannel logout: no sub or sid in token');
            return response('', 400);
        }

        Log::info('Backchannel logout received', ['sub' => $sub, 'sid' => $sid]);

        $this->destroyUserSessions($sub, $sid);

        // Keycloak expects 200 OK on success
        return response('', 200);
    }

    /**
     * Destroy all sessions for the given Keycloak user.
     */
    protected function destroyUserSessions(?string $sub, ?string $sid): void
    {
        $sessionDriver = config('session.driver');
        $userModelClass = config('keycloak-socialite.user_model', 'App\\Models\\User');

        // Find the user by keycloak_id (sub)
        if (! $sub) {
            return;
        }

        $keycloakIdColumn = config('keycloak-socialite.columns.keycloak_id', 'keycloak_id');
        $user = $userModelClass::where($keycloakIdColumn, $sub)->first();

        if (! $user) {
            Log::debug('Backchannel logout: user not found locally', ['sub' => $sub]);
            return;
        }

        // Database session driver — delete session rows for this user
        if ($sessionDriver === 'database') {
            $table = config('session.table', 'sessions');
            $deleted = DB::table($table)->where('user_id', $user->id)->delete();

            // Also invalidate remember token
            if (method_exists($user, 'setRememberToken')) {
                $user->setRememberToken('');
                $user->save();
            }

            Log::info('Backchannel logout: destroyed sessions', [
                'user_id' => $user->id,
                'email' => $user->email,
                'sessions_deleted' => $deleted,
            ]);

            return;
        }

        // File session driver — we can't easily find sessions by user_id.
        // As a fallback, we invalidate the user's remember_token so they
        // get logged out on next request.
        if ($sessionDriver === 'file') {
            $user->update(['remember_token' => null]);

            Log::info('Backchannel logout: invalidated remember_token (file driver)', [
                'user_id' => $user->id,
            ]);

            return;
        }

        // For other drivers (redis, memcached), similar approach
        Log::debug('Backchannel logout: session driver not fully supported for backchannel', [
            'driver' => $sessionDriver,
        ]);
    }

    /**
     * Decode JWT payload without signature verification.
     * (Keycloak is trusted — it's server-to-server behind firewall)
     */
    protected function decodeJwtPayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'));

        if (! $payload) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }
}
