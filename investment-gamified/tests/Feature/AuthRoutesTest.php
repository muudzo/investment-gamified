<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_requires_fields()
    {
        $response = $this->postJson('/api/auth/register', []);
        $response->assertStatus(422);
    }

    public function test_register_and_login_work()
    {
        $email = 'test+' . time() . '@example.com';

        // Register
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['success', 'user', 'token']);

        // Login
        $response = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'user', 'token']);

        $token = $response->json('token');

        // Get user with token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/user');

        $response->assertStatus(200);
        $response->assertJsonPath('user.email', $email);

        // Logout
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
