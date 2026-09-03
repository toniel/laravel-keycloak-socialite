<?php

namespace Toniel\LaravelKeycloakSocialite\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Toniel\LaravelKeycloakSocialite\Http\Middleware\AttemptKeycloakSso;
use Toniel\LaravelKeycloakSocialite\Tests\TestCase;

/**
 * Covers the auto_apply path, where the package pushes the middleware onto the
 * `web` group itself — including its position relative to `auth`.
 */
class SilentSsoAutoApplyTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Must be set before the package boots: that is when the middleware is
        // pushed onto the group and slotted into the priority list.
        $app['config']->set('keycloak-socialite.silent_sso.enabled', true);
        $app['config']->set('keycloak-socialite.silent_sso.auto_apply', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('login', fn () => 'Login page')->name('login');
        Route::get('protected-page', fn () => 'Protected')->middleware(['web', 'auth']);
        Route::get('open-page', fn () => 'Open')->middleware('web');
    }

    #[Test]
    public function it_is_pushed_onto_the_web_group(): void
    {
        $this->assertContains(
            AttemptKeycloakSso::class,
            $this->app['router']->getMiddlewareGroups()['web']
        );

        $this->get('/open-page')->assertRedirect(route('login.keycloak.silent-check'));
    }

    #[Test]
    public function it_runs_before_the_auth_middleware(): void
    {
        // Without the priority fix the guest would be sent to the login page
        // before the silent check ever had a chance to run.
        $this->get('/protected-page')
            ->assertRedirect(route('login.keycloak.silent-check'));
    }
}
