<?php

namespace Toniel\LaravelKeycloakSocialite\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakAuthenticationFailed;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakUserAuthenticated;
use Toniel\LaravelKeycloakSocialite\Events\KeycloakUserCreated;
use Toniel\LaravelKeycloakSocialite\Tests\Models\RedirectOverrideUser;
use Toniel\LaravelKeycloakSocialite\Tests\Models\TestUser;
use Toniel\LaravelKeycloakSocialite\Tests\TestCase;

class KeycloakControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('login', fn () => 'Login page')->name('login');
    }

    #[Test]
    public function it_redirects_to_keycloak_with_idp_hint(): void
    {
        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('with')->once()->andReturnSelf();
        $mockDriver->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://auth.example.com/realms/test-realm/protocol/openid-connect/auth?kc_idp_hint=google'));

        Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($mockDriver);

        $response = $this->get(route('login.keycloak'));

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');

        $this->assertStringContainsString('auth.example.com', $redirectUrl);
        $this->assertStringContainsString('test-realm', $redirectUrl);
        $this->assertStringContainsString('kc_idp_hint=google', $redirectUrl);
    }

    #[Test]
    public function it_handles_callback_and_authenticates_existing_user(): void
    {
        Event::fake();

        $existingUser = TestUser::create([
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => bcrypt('secret'),
        ]);

        $socialiteUser = $this->mockSocialiteUser('john@example.com');
        $this->mockSocialiteDriverForUser($socialiteUser);

        $response = $this->get(route('login.keycloak.callback'));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertEquals($existingUser->id, Auth::id());

        Event::assertDispatched(KeycloakUserAuthenticated::class);
        Event::assertNotDispatched(KeycloakUserCreated::class);
    }

    #[Test]
    public function it_handles_callback_and_creates_new_user_when_auto_register_enabled(): void
    {
        Event::fake();

        $socialiteUser = $this->mockSocialiteUser('newuser@example.com');
        $this->mockSocialiteDriverForUser($socialiteUser);

        $response = $this->get(route('login.keycloak.callback'));

        $response->assertRedirect('/dashboard');
        $this->assertTrue(Auth::check());

        $user = TestUser::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('456', $user->keycloak_id);

        Event::assertDispatched(KeycloakUserCreated::class);
        Event::assertNotDispatched(KeycloakUserAuthenticated::class);
    }

    #[Test]
    public function it_rejects_unknown_user_when_auto_register_disabled(): void
    {
        config(['keycloak-socialite.auto_register' => false]);
        Event::fake();

        $socialiteUser = $this->mockSocialiteUser('unknown@example.com');
        $this->mockSocialiteDriverForUser($socialiteUser);

        $response = $this->get(route('login.keycloak.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertFalse(Auth::check());

        Event::assertDispatched(KeycloakAuthenticationFailed::class);
    }

    #[Test]
    public function it_handles_socialite_exception_on_callback(): void
    {
        Event::fake();

        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('OAuth error'));

        Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($mockDriver);

        $response = $this->get(route('login.keycloak.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertFalse(Auth::check());

        Event::assertDispatched(KeycloakAuthenticationFailed::class);
    }

    #[Test]
    public function event_can_override_redirect_url(): void
    {
        Event::listen(KeycloakUserAuthenticated::class, function (KeycloakUserAuthenticated $event) {
            $event->redirectUrl = '/admin/dashboard';
        });

        TestUser::create([
            'name'     => 'Jane Doe',
            'email'    => 'jane@example.com',
            'password' => bcrypt('secret'),
        ]);

        $socialiteUser = $this->mockSocialiteUser('jane@example.com');
        $this->mockSocialiteDriverForUser($socialiteUser);

        $response = $this->get(route('login.keycloak.callback'));

        $response->assertRedirect('/admin/dashboard');
    }

    #[Test]
    public function user_model_can_provide_redirect_url(): void
    {
        config(['keycloak-socialite.user_model' => RedirectOverrideUser::class]);

        RedirectOverrideUser::create([
            'name'     => 'Custom User',
            'email'    => 'custom@example.com',
            'password' => bcrypt('secret'),
        ]);

        $socialiteUser = $this->mockSocialiteUser('custom@example.com');
        $this->mockSocialiteDriverForUser($socialiteUser);

        $response = $this->get(route('login.keycloak.callback'));

        $response->assertRedirect('/custom-dashboard');
    }

    #[Test]
    public function it_handles_local_logout(): void
    {
        config(['keycloak-socialite.logout.mode' => 'local']);

        $user = TestUser::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('logout.keycloak'));

        // Local mode: redirect to app URL (absolute)
        $response->assertRedirect('http://localhost/');
        $this->assertFalse(Auth::check());
    }

    #[Test]
    public function it_handles_keycloak_logout_with_id_token_hint(): void
    {
        config(['keycloak-socialite.logout.mode' => 'keycloak']);
        config(['keycloak-socialite.logout.redirect_url' => '/']);
        config(['keycloak-socialite.logout.id_token_hint' => true]);

        $user = TestUser::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['keycloak_id_token' => 'test-id-token'])
            ->get(route('logout.keycloak'));

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');

        $this->assertStringContainsString('auth.example.com', $redirectUrl);
        $this->assertStringContainsString('test-realm', $redirectUrl);
        $this->assertStringContainsString('id_token_hint=test-id-token', $redirectUrl);
        $this->assertStringContainsString('post_logout_redirect_uri=', $redirectUrl);
        $this->assertStringContainsString('client_id=test-client', $redirectUrl);
        $this->assertFalse(Auth::check());
    }

    #[Test]
    public function it_handles_keycloak_logout_without_id_token_hint(): void
    {
        config(['keycloak-socialite.logout.mode' => 'keycloak']);
        config(['keycloak-socialite.logout.redirect_url' => '/']);
        config(['keycloak-socialite.logout.id_token_hint' => false]);

        $user = TestUser::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('logout.keycloak'));

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');

        $this->assertStringContainsString('auth.example.com', $redirectUrl);
        $this->assertStringContainsString('test-realm', $redirectUrl);
        $this->assertStringContainsString('client_id=test-client', $redirectUrl);
        $this->assertStringNotContainsString('id_token_hint', $redirectUrl);
        $this->assertFalse(Auth::check());
    }

    /**
     * Create a mock Socialite user.
     */
    protected function mockSocialiteUser(string $email): SocialiteUser
    {
        $mock = Mockery::mock(SocialiteUser::class);

        $mock->shouldReceive('getEmail')->andReturn($email);
        $mock->shouldReceive('getName')->andReturn('Test User');
        $mock->shouldReceive('getId')->andReturn('456');
        $mock->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
        $mock->shouldReceive('getRaw')->andReturn(['id_token' => 'test-id-token']);

        return $mock;
    }

    /**
     * Setup Socialite mock to return a specific user.
     */
    protected function mockSocialiteDriverForUser(SocialiteUser $user): void
    {
        $mockDriver = Mockery::mock();
        $mockDriver->shouldReceive('user')->once()->andReturn($user);

        Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($mockDriver);
    }
}
