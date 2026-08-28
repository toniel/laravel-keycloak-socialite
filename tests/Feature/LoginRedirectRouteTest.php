<?php

namespace Toniel\LaravelKeycloakSocialite\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Toniel\LaravelKeycloakSocialite\Tests\TestCase;

class LoginRedirectRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('keycloak-socialite.routes.auto_login_redirect', true);
    }

    #[Test]
    public function it_registers_login_route_and_redirects_to_keycloak_when_enabled(): void
    {
        $this->assertTrue(Route::has('login'));

        $this->get('/login')->assertRedirect('/auth/keycloak');
    }
}
