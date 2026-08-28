<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The fully-qualified class name of your application's User model. It must
    | implement Toniel\LaravelKeycloakSocialite\Contracts\KeycloakAuthenticatable.
    |
    */
    'user_model' => env('KEYCLOAK_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Default Redirect URL
    |--------------------------------------------------------------------------
    |
    | Fallback URL when the User model's getKeycloakRedirectUrl() returns null,
    | or when events do not override the redirect URL.
    |
    */
    'redirect_url' => env('KEYCLOAK_REDIRECT_URL', '/dashboard'),

    /*
    |--------------------------------------------------------------------------
    | Identity Provider Hint
    |--------------------------------------------------------------------------
    |
    | The kc_idp_hint parameter passed to the Keycloak authorization endpoint.
    | Set to null or empty string to skip sending any hint.
    | Common values: 'google', 'facebook', 'github', etc.
    |
    | NOTE: Setting this to an IdP alias (e.g. 'google') sends users straight
    | to that IdP, skipping the Keycloak login page. The realm-level equivalent
    | is Keycloak's "Authenticate by default" toggle
    | (Realm → Identity Providers → <idp> → Authenticate by default). If
    | neither is set, Keycloak shows its own login page.
    |
    */
    'idp_hint' => env('KEYCLOAK_IDP_HINT', 'google'),

    /*
    |--------------------------------------------------------------------------
    | Auto Register
    |--------------------------------------------------------------------------
    |
    | When true, the callback will create a new User record if no existing user
    | is found by email. When false, authentication fails for unknown emails.
    |
    */
    'auto_register' => env('KEYCLOAK_AUTO_REGISTER', true),

    /*
    |--------------------------------------------------------------------------
    | Remember Login
    |--------------------------------------------------------------------------
    |
    | Pass the "remember" parameter to Auth::login() so the session persists
    | across browser restarts.
    |
    */
    'remember_login' => env('KEYCLOAK_REMEMBER_LOGIN', false),

    /*
    |--------------------------------------------------------------------------
    | Keycloak Server Configuration
    |--------------------------------------------------------------------------
    |
    | These values are consumed by socialiteproviders/keycloak to construct
    | the OAuth2 / OpenID Connect URLs.
    |
    */
    'keycloak' => [
        'base_url'      => env('KEYCLOAK_BASE_URL'),
        'realms'        => env('KEYCLOAK_REALM', 'aiu'),
        'client_id'     => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect'      => env('KEYCLOAK_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Column Names
    |--------------------------------------------------------------------------
    |
    | Override if your users table uses different column names.
    | These are used by the HasKeycloakIdentity trait and the published
    | migration stub.
    |
    */
    'columns' => [
        'keycloak_id'     => 'keycloak_id',
        'keycloak_avatar' => 'keycloak_avatar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | enabled: Set to false to define your own routes manually.
    | redirect/callback/logout: URI paths for each endpoint.
    | auto_login_redirect: Register a `login` route that forwards to Keycloak
    |   instead of the app's own login page.
    | redirect_as/callback_as/logout_as: Named route names.
    |
    */
    'routes' => [
        'enabled'     => true,

        'redirect'    => 'auth/keycloak',
        'callback'    => 'auth/keycloak/callback',
        'logout'      => 'auth/keycloak/logout',
        'backchannel_logout' => 'auth/keycloak/backchannel-logout',

        'auto_login_redirect' => env('KEYCLOAK_AUTO_LOGIN_REDIRECT', false),
        'login'               => 'login',
        'login_as'            => 'login',

        'redirect_as' => 'login.keycloak',
        'callback_as' => 'login.keycloak.callback',
        'logout_as'   => 'logout.keycloak',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logout Configuration
    |--------------------------------------------------------------------------
    |
    | mode:
    |   'local'    — Destroy the local Laravel session only.
    |                No redirect to Keycloak. The user stays logged into
    |                Keycloak's SSO session.
    |   'keycloak' — Destroy both the local session AND the Keycloak SSO
    |                session. The user is redirected to Keycloak's logout
    |                endpoint, which then redirects back to 'redirect_url'.
    |                When 'id_token_hint' is true, the id_token obtained
    |                during login is sent so Keycloak skips its confirmation
    |                page.
    |
    | redirect_url: Used in 'keycloak' mode as the post_logout_redirect_uri.
    |               Must be registered in Keycloak's "Valid Post Logout
    |               Redirect URIs" for this client.
    |
    | id_token_hint: When true (and mode is 'keycloak'), sends the id_token
    |                so Keycloak logs out silently without asking the user
    |                to confirm.
    |
    */
    'logout' => [
        'mode'                => env('KEYCLOAK_LOGOUT_MODE', 'keycloak'),
        'redirect_url'        => env('KEYCLOAK_LOGOUT_REDIRECT', '/'),
        'id_token_hint'       => env('KEYCLOAK_LOGOUT_ID_TOKEN_HINT', true),
        'backchannel_enabled' => env('KEYCLOAK_BACKCHANNEL_LOGOUT', true),
    ],

];
