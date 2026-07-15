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
    'remember_login' => env('KEYCLOAK_REMEMBER_LOGIN', true),

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
    | redirect_as/callback_as/logout_as: Named route names.
    |
    */
    'routes' => [
        'enabled'     => true,

        'redirect'    => 'auth/keycloak',
        'callback'    => 'auth/keycloak/callback',
        'logout'      => 'auth/keycloak/logout',

        'redirect_as' => 'login.keycloak',
        'callback_as' => 'login.keycloak.callback',
        'logout_as'   => 'logout.keycloak',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logout Configuration
    |--------------------------------------------------------------------------
    |
    | redirect_after_logout: Where to send the user after they are logged out
    |                        from both Laravel and Keycloak.
    | redirect_to_keycloak: The Keycloak logout endpoint. Use null to construct
    |                       it automatically from base_url and realms.
    |
    */
    'logout' => [
        'redirect_after_logout' => env('KEYCLOAK_LOGOUT_REDIRECT', '/'),
    ],

];
