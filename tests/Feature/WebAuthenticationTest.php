<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_is_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login', false));
    }

    public function test_user_can_login_with_web_session(): void
    {
        $user = User::factory()->create([
            'email' => 'web-user@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'web-user@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_web_credentials(): void
    {
        User::factory()->create([
            'email' => 'web-user@example.com',
            'password' => 'password',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'web-user@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout_web_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_forgot_password_sends_generic_response_and_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post('/forgot-password', [
            'email' => 'reset@example.com',
        ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Se o e-mail existir, enviaremos as instruções de redefinição.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password_from_web_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'old-password',
        ]);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_api_token_login_still_works_after_web_auth_routes(): void
    {
        User::factory()->create([
            'email' => 'api-user@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'api-user@example.com',
            'password' => 'password',
            'device_name' => 'Mobile',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'user' => ['id', 'name', 'email']],
            ]);
    }
}
