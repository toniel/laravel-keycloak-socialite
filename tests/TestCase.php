<?php

namespace Toniel\LaravelKeycloakSocialite\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Toniel\LaravelKeycloakSocialite\KeycloakSocialiteServiceProvider::class,
            \Laravel\Socialite\SocialiteServiceProvider::class,
            \SocialiteProviders\Manager\ServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use memory SQLite
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Socialite config
        $app['config']->set('services.keycloak', [
            'base_url'      => 'https://auth.example.com',
            'realms'        => 'test-realm',
            'client_id'     => 'test-client',
            'client_secret' => 'test-secret',
            'redirect'      => 'http://localhost/auth/keycloak/callback',
        ]);

        // Keycloak package config
        $app['config']->set('keycloak-socialite.keycloak', [
            'base_url'      => 'https://auth.example.com',
            'realms'        => 'test-realm',
            'client_id'     => 'test-client',
            'client_secret' => 'test-secret',
            'redirect'      => 'http://localhost/auth/keycloak/callback',
        ]);

        $app['config']->set('keycloak-socialite.idp_hint', 'google');
        $app['config']->set('keycloak-socialite.auto_register', true);
        $app['config']->set('keycloak-socialite.remember_login', false);
        $app['config']->set('keycloak-socialite.routes.enabled', true);
        $app['config']->set('keycloak-socialite.user_model', \Toniel\LaravelKeycloakSocialite\Tests\Models\TestUser::class);
    }

    /**
     * Setup test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // create users table in memory
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('keycloak_id')->nullable()->unique();
            $table->string('keycloak_avatar')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
