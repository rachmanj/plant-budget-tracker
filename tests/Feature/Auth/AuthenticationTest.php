<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'auth@pmb.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'auth@pmb.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_authenticate_with_username(): void
    {
        $user = User::factory()->create([
            'email' => 'auth@pmb.test',
            'username' => 'authuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'authuser',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_username_login_is_case_insensitive(): void
    {
        $user = User::factory()->create([
            'email' => 'auth@pmb.test',
            'username' => 'authuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'AuthUser',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'auth@pmb.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'auth@pmb.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invalid_password_uses_same_error_message_for_username_login(): void
    {
        User::factory()->create([
            'email' => 'auth@pmb.test',
            'username' => 'authuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $emailResponse = $this->post('/login', [
            'email' => 'auth@pmb.test',
            'password' => 'wrong-password',
        ]);

        $usernameResponse = $this->post('/login', [
            'email' => 'authuser',
            'password' => 'wrong-password',
        ]);

        $emailResponse->assertSessionHasErrors([
            'email' => __('auth.failed'),
        ]);
        $usernameResponse->assertSessionHasErrors([
            'email' => __('auth.failed'),
        ]);
        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        User::factory()->create([
            'email' => 'inactive@pmb.test',
            'password' => bcrypt('password'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@pmb.test',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate_with_username(): void
    {
        User::factory()->create([
            'email' => 'inactive@pmb.test',
            'username' => 'inactiveuser',
            'password' => bcrypt('password'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactiveuser',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This account is inactive.',
        ]);
        $this->assertGuest();
    }

    public function test_duplicate_username_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'first@pmb.test',
            'username' => 'duplicate',
        ]);

        $this->expectException(ValidationException::class);

        User::factory()->create([
            'email' => 'second@pmb.test',
            'username' => 'duplicate',
        ]);
    }

    public function test_username_may_not_look_like_an_email_address(): void
    {
        $this->expectException(ValidationException::class);

        User::factory()->create([
            'email' => 'valid@pmb.test',
            'username' => 'looks.like@email.com',
        ]);
    }

    public function test_login_is_limited_to_five_attempts_per_minute(): void
    {
        User::factory()->create([
            'email' => 'auth@pmb.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'auth@pmb.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'auth@pmb.test',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
