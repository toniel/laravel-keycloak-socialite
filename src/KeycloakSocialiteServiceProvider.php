<?php

namespace Toniel\LaravelKeycloakSocialite;

use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Toniel\LaravelKeycloakSocialite\Http\Middleware\AttemptKeycloakSso;
use Toniel\LaravelKeycloakSocialite\Providers\KeycloakWithIdToken;

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

        // Register the Keycloak Socialite provider — use our custom provider
        // that captures the id_token for silent logout.
        Event::listen(SocialiteWasCalled::class, function () {
            Socialite::extend('keycloak', function () {
                $config = config('keycloak-socialite.keycloak');
                $socialConfig = new \SocialiteProviders\Manager\Config(
                    $config['client_id'],
                    $config['client_secret'],
                    $config['redirect'],
                    ['base_url' => $config['base_url'], 'realms' => $config['realms']]
                );

                $provider = new KeycloakWithIdToken(
                    app('request'),
                    $config['client_id'],
                    $config['client_secret'],
                    $config['redirect']
                );
                $provider->setConfig($socialConfig);

                return $provider;
            });
        }, -10); // priority -10 :: runs AFTER the default listener, so ours wins

        // Load routes (if enabled in config)
        if (config('keycloak-socialite.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        $this->registerSilentSsoMiddleware();
    }

    /**
     * Register the silent SSO middleware.
     *
     * The alias is always available so apps can apply it to selected routes;
     * when silent SSO is enabled with auto_apply, it is also pushed onto the
     * `web` group so every page load can pick up an existing SSO session.
     */
    protected function registerSilentSsoMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('keycloak.sso', AttemptKeycloakSso::class);

        if (config('keycloak-socialite.silent_sso.enabled', false)
            && config('keycloak-socialite.silent_sso.auto_apply', true)) {
            $this->applySilentSsoMiddleware($router);
        }
    }

    /**
     * Add the silent check to the `web` group, ahead of `auth`.
     *
     * Registering through the HTTP kernel rather than the router matters:
     * the kernel re-syncs its own middleware groups onto the router when it
     * boots, which would drop a group entry pushed straight onto the router.
     * `auth` also sits in the framework's middleware priority list, so it is
     * pulled ahead of unlisted middleware — guests would be bounced to the
     * login page before the silent check ever ran.
     */
    protected function applySilentSsoMiddleware(Router $router): void
    {
        $kernel = $this->app->bound(HttpKernel::class)
            ? $this->app->make(HttpKernel::class)
            : null;

        if ($kernel && method_exists($kernel, 'appendMiddlewareToGroup')) {
            $kernel->appendMiddlewareToGroup('web', AttemptKeycloakSso::class);
        } else {
            $router->pushMiddlewareToGroup('web', AttemptKeycloakSso::class);
        }

        if ($kernel && method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
            $kernel->addToMiddlewarePriorityBefore(
                AuthenticatesRequests::class,
                AttemptKeycloakSso::class
            );
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
