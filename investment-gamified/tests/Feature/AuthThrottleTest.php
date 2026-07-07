<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_limiter_is_registered_and_returns_a_limit(): void
    {
        $limiter = RateLimiter::limiter('login');

        $this->assertIsCallable($limiter);

        $request = Request::create('/api/auth/login', 'POST', [
            'email' => 'someone@example.com',
        ]);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $result = $limiter($request);

        $this->assertInstanceOf(Limit::class, $result);
        $this->assertSame(5, $result->maxAttempts);
    }

    public function test_login_limiter_falls_back_to_ip_when_email_missing(): void
    {
        $limiter = RateLimiter::limiter('login');

        $request = Request::create('/api/auth/login', 'POST', []);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $result = $limiter($request);

        $this->assertInstanceOf(Limit::class, $result);
        $this->assertSame(5, $result->maxAttempts);
    }

    public function test_register_limiter_is_registered_and_returns_a_limit(): void
    {
        $limiter = RateLimiter::limiter('register');

        $this->assertIsCallable($limiter);

        $request = Request::create('/api/auth/register', 'POST', []);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $result = $limiter($request);

        $this->assertInstanceOf(Limit::class, $result);
        $this->assertSame(5, $result->maxAttempts);
    }

    public function test_api_limiter_is_registered_and_returns_a_limit(): void
    {
        $limiter = RateLimiter::limiter('api');

        $this->assertIsCallable($limiter);

        $request = Request::create('/api/some-endpoint', 'GET', []);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $result = $limiter($request);

        $this->assertInstanceOf(Limit::class, $result);
        $this->assertSame(120, $result->maxAttempts);
    }

    public function test_sanctum_expiration_config_is_set(): void
    {
        $this->assertNotNull(config('sanctum.expiration'));
    }

    public function test_register_returns_created_with_token(): void
    {
        $email = 'register+'.time().'@example.com';

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['success', 'user', 'token']);
    }

    public function test_login_returns_ok_with_token_and_prunes_prior_tokens(): void
    {
        $email = 'login+'.time().'@example.com';

        $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $user = User::where('email', $email)->firstOrFail();

        // First login issues a second 'auth-token' alongside the one from
        // registration.
        $firstLogin = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);
        $firstLogin->assertStatus(200);
        $firstLogin->assertJsonStructure(['success', 'user', 'token']);

        // Second login must prune the token(s) issued previously so only
        // the newest 'auth-token' survives.
        $secondLogin = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);
        $secondLogin->assertStatus(200);
        $secondLogin->assertJsonStructure(['success', 'user', 'token']);

        $this->assertSame(
            1,
            $user->tokens()->where('name', 'auth-token')->count()
        );
    }
}
