<?php

namespace Toniel\LaravelKeycloakSocialite;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class KeycloakSocialiteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/keycloak-socialite.php' => config_path('keycloak-socialite.php'),
        ], 'keycloak-socialite-config');

        // Publish migration
        $this->publishes([
            __DIR__ . '/../database/migrations/add_keycloak_fields_to_users_table.php.stub'
                => database_path('migrations/' . date('Y_m_d_His') . '_add_keycloak_fields_to_users_table.php'),
        ], 'keycloak-socialite-migrations');

        // Register the Keycloak Socialite provider automatically
        Event::listen(SocialiteWasCalled::class, KeycloakExtendSocialite::class);

        // Load routes (if enabled in config)
        if (config('keycloak-socialite.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        // Merge default config with the consumer's published config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/keycloak-socialite.php',
            'keycloak-socialite'
        );

        // Ensure socialiteproviders/keycloak can read its config.
        // It reads from services.keycloak by convention, so we map our config there.
        $config = $this->app->make('config');

        $config->set('services.keycloak', array_merge(
            $config->get('services.keycloak', []),
            $config->get('keycloak-socialite.keycloak', [])
        ));
    }
}
