<?php

use Illuminate\Support\Facades\Route;
use Toniel\LaravelKeycloakSocialite\Http\Controllers\HandleKeycloakCallbackController;
use Toniel\LaravelKeycloakSocialite\Http\Controllers\HandleKeycloakLogoutController;
use Toniel\LaravelKeycloakSocialite\Http\Controllers\RedirectToKeycloakController;

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
