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
