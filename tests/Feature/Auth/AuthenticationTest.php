<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_active_user_can_authenticate_using_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser_'.uniqid(),
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $response->assertSessionHas('success', function (string $message) use ($user) {
            return str_contains($message, $user->name) && str_contains($message, 'berhasil masuk');
        });
    }

    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser_'.uniqid(),
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_user_with_status_zero_cannot_login(): void
    {
        $inactiveUser = User::factory()->create([
            'username' => 'inactive_'.uniqid(),
            'password' => Hash::make('password123'),
            'status' => 0,
        ]);

        $response = $this->post('/login', [
            'username' => $inactiveUser->username,
            'password' => 'password123',
        ]);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_access_protected_admin_routes(): void
    {
        $user = User::factory()->create([
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_protected_admin_routes(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
