<?php

namespace Toniel\LaravelKeycloakSocialite\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakAuthenticationFailed;
use Toniel\LaravelKeycloakSocialite\Http\Middleware\AttemptKeycloakSso;
use Toniel\LaravelKeycloakSocialite\Support\SilentSso;
use Toniel\LaravelKeycloakSocialite\Tests\Models\TestUser;
use Toniel\LaravelKeycloakSocialite\Tests\TestCase;

class SilentSsoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('keycloak-socialite.silent_sso.enabled', true);
        config()->set('keycloak-socialite.silent_sso.retry_after', 600);

        Route::get('login', fn () => 'Login page')->name('login');

        Route::middleware(['web', AttemptKeycloakSso::class])->group(function () {
            Route::get('public-page', fn () => 'Public page')->name('public-page');
            Route::post('public-page', fn () => 'Posted')->name('public-page.store');
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_sends_a_guest_to_the_silent_check(): void
    {
        $response = $this->get('/public-page');

        $response->assertRedirect(route('login.keycloak.silent-check'));
        $response->assertSessionHas(SilentSso::SESSION_RETURN_TO, url('/public-page'));
    }

    #[Test]
    public function it_does_not_check_an_authenticated_user(): void
    {
        $user = TestUser::create([
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user)->get('/public-page')->assertOk();
    }

    #[Test]
    public function it_does_not_check_twice_within_the_retry_window(): void
    {
        $this->withSession([SilentSso::SESSION_CHECKED_AT => now()->getTimestamp()])
            ->get('/public-page')
            ->assertOk();
    }

    #[Test]
    public function it_checks_again_once_the_retry_window_has_passed(): void
    {
        $this->withSession([SilentSso::SESSION_CHECKED_AT => now()->getTimestamp() - 601])
            ->get('/public-page')
            ->assertRedirect(route('login.keycloak.silent-check'));
    }

    #[Test]
    public function retry_after_zero_checks_only_once_per_session(): void
    {
        config()->set('keycloak-socialite.silent_sso.retry_after', 0);

        $this->withSession([SilentSso::SESSION_CHECKED_AT => now()->getTimestamp() - 99999])
            ->get('/public-page')
            ->assertOk();
    }

    #[Test]
    public function it_does_not_check_on_non_get_requests(): void
    {
        $this->post('/public-page')->assertOk();
    }

    #[Test]
    public function it_does_not_check_on_json_requests(): void
    {
        $this->getJson('/public-page')->assertOk();
    }

    #[Test]
    public function it_does_not_check_when_disabled(): void
    {
        config()->set('keycloak-socialite.silent_sso.enabled', false);

        $this->get('/public-page')->assertOk();
    }

    #[Test]
    public function it_does_not_intercept_the_packages_own_routes(): void
    {
        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('with')->once()->andReturnSelf();
        $mockDriver->shouldReceive('redirect')->once()->andReturn(redirect('https://auth.example.com/auth'));

        Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($mockDriver);

        $this->get(route('login.keycloak'))
            ->assertRedirect('https://auth.example.com/auth');
    }

    #[Test]
    public function it_skips_the_login_route_when_auto_login_redirect_is_on(): void
    {
        config()->set('keycloak-socialite.routes.auto_login_redirect', true);

        Route::get('sso-login', fn () => 'Forwarded')
            ->middleware(['web', AttemptKeycloakSso::class]);
        config()->set('keycloak-socialite.routes.login', 'sso-login');

        $this->get('/sso-login')->assertOk();
    }

    #[Test]
    public function it_honours_extra_except_patterns(): void
    {
        config()->set('keycloak-socialite.silent_sso.except', ['public-page']);

        $this->get('/public-page')->assertOk();
    }

    #[Test]
    public function the_check_redirects_to_keycloak_with_prompt_none(): void
    {
        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('with')
            ->once()
            ->with(['prompt' => 'none'])
            ->andReturnSelf();
        $mockDriver->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://auth.example.com/auth?prompt=none'));

        Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($mockDriver);

        $response = $this->withSession([SilentSso::SESSION_RETURN_TO => url('/public-page')])
            ->get(route('login.keycloak.silent-check'));

        $response->assertRedirect('https://auth.example.com/auth?prompt=none');
        $response->assertSessionHas(SilentSso::SESSION_SILENT, true);
        $response->assertSessionHas('url.intended', url('/public-page'));
        $this->assertNotNull(session(SilentSso::SESSION_CHECKED_AT));
    }

    #[Test]
    public function the_check_keeps_an_intended_url_set_by_the_auth_middleware(): void
    {
        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('with')->once()->andReturnSelf();
        $mockDriver->shouldReceive('redirect')->once()->andReturn(redirect('https://auth.example.com/auth'));

        Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($mockDriver);

        $this->withSession([
            'url.intended'               => url('/protected-page'),
            SilentSso::SESSION_RETURN_TO => url('/public-page'),
        ])->get(route('login.keycloak.silent-check'))
            ->assertSessionHas('url.intended', url('/protected-page'));
    }

    #[Test]
    public function the_check_skips_keycloak_when_already_authenticated(): void
    {
        $user = TestUser::create([
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user)
            ->withSession([SilentSso::SESSION_RETURN_TO => url('/public-page')])
            ->get(route('login.keycloak.silent-check'))
            ->assertRedirect(url('/public-page'));
    }

    #[Test]
    public function login_required_returns_the_guest_to_their_page_without_an_error(): void
    {
        Event::fake();

        $response = $this->withSession([
            SilentSso::SESSION_SILENT    => true,
            SilentSso::SESSION_RETURN_TO => url('/public-page'),
        ])->get(route('login.keycloak.callback', ['error' => 'login_required']));

        $response->assertRedirect(url('/public-page'));
        $response->assertSessionMissing('error');
        $response->assertSessionMissing(SilentSso::SESSION_SILENT);
        $this->assertNotNull(session(SilentSso::SESSION_CHECKED_AT));
        $this->assertGuest();

        Event::assertNotDispatched(KeycloakAuthenticationFailed::class);
    }

    #[Test]
    public function a_real_error_during_a_silent_check_still_returns_the_guest_quietly(): void
    {
        Event::fake();

        $this->withSession([
            SilentSso::SESSION_SILENT    => true,
            SilentSso::SESSION_RETURN_TO => url('/public-page'),
        ])->get(route('login.keycloak.callback', ['error' => 'access_denied']))
            ->assertRedirect(url('/public-page'))
            ->assertSessionMissing('error');

        Event::assertDispatched(KeycloakAuthenticationFailed::class);
    }

    #[Test]
    public function an_error_on_an_interactive_login_goes_to_the_login_page(): void
    {
        Event::fake();

        $this->get(route('login.keycloak.callback', [
            'error'             => 'access_denied',
            'error_description' => 'User denied access',
        ]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        Event::assertDispatched(KeycloakAuthenticationFailed::class);
    }

    #[Test]
    public function a_successful_silent_check_logs_the_user_in_and_clears_the_state(): void
    {
        TestUser::create([
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => bcrypt('secret'),
        ]);

        $socialiteUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $socialiteUser->shouldReceive('getEmail')->andReturn('john@example.com');
        $socialiteUser->shouldReceive('getId')->andReturn('keycloak-uuid-1');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');
        $socialiteUser->shouldReceive('getRaw')->andReturn([]);
        $socialiteUser->accessTokenResponseBody = ['id_token' => 'fake-id-token'];

        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($mockDriver);

        $response = $this->withSession([
            SilentSso::SESSION_SILENT    => true,
            SilentSso::SESSION_RETURN_TO => url('/public-page'),
            'url.intended'               => url('/public-page'),
        ])->get(route('login.keycloak.callback', ['code' => 'auth-code', 'state' => 'state']));

        $response->assertRedirect(url('/public-page'));
        $response->assertSessionMissing(SilentSso::SESSION_SILENT);
        $response->assertSessionMissing(SilentSso::SESSION_RETURN_TO);

        $this->assertTrue(Auth::check());
        $this->assertSame('john@example.com', Auth::user()->email);
    }
}
