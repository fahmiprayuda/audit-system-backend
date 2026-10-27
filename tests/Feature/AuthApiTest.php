<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test.auditor@audit.com',
            'password' => Hash::make('secret123'),
            'role' => 'auditor',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test.auditor@audit.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ]);

        $this->assertEquals('auditor', $response->json('user.role'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'valid@audit.com',
            'password' => Hash::make('correct_password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'valid@audit.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'John Auditor',
            'email' => 'john@audit.com',
            'role' => 'auditor',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'John Auditor',
                'email' => 'john@audit.com',
            ]);
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
