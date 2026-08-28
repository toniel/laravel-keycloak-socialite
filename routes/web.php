<?php

use Illuminate\Support\Facades\Route;
use Toniel\LaravelKeycloakSocialite\Http\Controllers\HandleBackchannelLogoutController;
use Toniel\LaravelKeycloakSocialite\Http\Controllers\HandleKeycloakCallbackController;
use Toniel\LaravelKeycloakSocialite\Http\Controllers\HandleKeycloakLogoutController;
use Toniel\LaravelKeycloakSocialite\Http\Controllers\RedirectToKeycloakController;

Route::middleware(['web'])->group(function () {
    Route::get(
        config('keycloak-socialite.routes.redirect', 'auth/keycloak'),
        RedirectToKeycloakController::class
    )->name(config('keycloak-socialite.routes.redirect_as', 'login.keycloak'));

    Route::get(
        config('keycloak-socialite.routes.callback', 'auth/keycloak/callback'),
        HandleKeycloakCallbackController::class
    )->name(config('keycloak-socialite.routes.callback_as', 'login.keycloak.callback'));

    Route::get(
        config('keycloak-socialite.routes.logout', 'auth/keycloak/logout'),
        HandleKeycloakLogoutController::class
    )->name(config('keycloak-socialite.routes.logout_as', 'logout.keycloak'));
});

/*
|--------------------------------------------------------------------------
| Keycloak Backchannel Logout (server-to-server, no CSRF / session needed)
|--------------------------------------------------------------------------
|
| Keycloak POSTs a logout_token here when a user logs out from any client.
| This allows all apps to destroy local sessions simultaneously.
|
| Configure in Keycloak Admin → Client → Advanced → Backchannel Logout URL:
|   https://your-app.com/auth/keycloak/backchannel-logout
|
*/
if (config('keycloak-socialite.logout.backchannel_enabled', true)) {
    Route::post(
        config('keycloak-socialite.routes.backchannel_logout', 'auth/keycloak/backchannel-logout'),
        HandleBackchannelLogoutController::class
    )->name('login.keycloak.backchannel-logout')
     ->withoutMiddleware(['web', \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
}

/*
|--------------------------------------------------------------------------
| Optional guest login redirect
|--------------------------------------------------------------------------
|
| When enabled, the package registers a `login` named route that redirects
| to the Keycloak authorization endpoint. Laravel's `auth` middleware sends
| unauthenticated users here, so they land straight on Keycloak (e.g. Google)
| instead of the app's own login page.
|
| Remove any /login route your app defines to avoid a duplicate route name/URI.
|
*/
if (config('keycloak-socialite.routes.auto_login_redirect', false)) {
    Route::redirect(
        config('keycloak-socialite.routes.login', 'login'),
        '/'.ltrim(config('keycloak-socialite.routes.redirect', 'auth/keycloak'), '/')
    )->name(config('keycloak-socialite.routes.login_as', 'login'));
}
