<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_always_creates_a_client_and_token_works_for_current_user(): void
    {
        $response = $this->postJson('/api/register', [
            'nom' => 'New Customer',
            'email' => 'customer@example.com',
            'mot_de_passe' => 'password',
            'role' => 'admin',
        ])->assertCreated()
            ->assertJsonPath('user.role', 'client');

        $this->withToken($response->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'customer@example.com');
    }

    public function test_login_rejects_invalid_credentials_and_accepts_valid_credentials(): void
    {
        User::create([
            'nom' => 'Customer',
            'email' => 'customer@example.com',
            'mot_de_passe' => bcrypt('correct-password'),
            'role' => 'client',
        ]);

        $this->postJson('/api/login', [
            'email' => 'customer@example.com',
            'mot_de_passe' => 'wrong-password',
        ])->assertUnauthorized();

        $this->postJson('/api/login', [
            'email' => 'customer@example.com',
            'mot_de_passe' => 'correct-password',
        ])->assertOk()->assertJsonPath('user.role', 'client');
    }

    public function test_client_cannot_access_admin_api(): void
    {
        $client = User::create([
            'nom' => 'Customer',
            'email' => 'customer@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'client',
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertForbidden();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::create([
            'nom' => 'Customer',
            'email' => 'customer@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'client',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
