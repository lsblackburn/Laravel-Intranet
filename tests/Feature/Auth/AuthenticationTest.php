<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \iamfarhad\LaravelAuditLog\Drivers\MySQLDriver::clearCache();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_screen_clears_pending_two_factor_challenge_state(): void
    {
        $response = $this->withSession([
            '2fa:user_id' => 123,
            '2fa:remember' => true,
        ])->get('/login');

        $response->assertStatus(200);
        $response->assertSessionMissing('2fa:user_id');
        $response->assertSessionMissing('2fa:remember');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('audit_users_logs', [
            'entity_id' => (string) $user->id,
            'causer_id' => (string) $user->id,
            'action' => 'user_logged_in',
        ]);
        $entry = \Illuminate\Support\Facades\DB::table('audit_users_logs')->first();
        $this->assertSame("{$user->name} has logged in successfully.", json_decode($entry->metadata, true)['message']);
    }

    public function test_non_two_factor_login_clears_stale_pending_two_factor_challenge_state(): void
    {
        $user = User::factory()->create();
        $staleTwoFactorUser = User::factory()->create();

        $response = $this->withSession([
            '2fa:user_id' => $staleTwoFactorUser->id,
            '2fa:remember' => true,
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionMissing('2fa:user_id');
        $response->assertSessionMissing('2fa:remember');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(RateLimiter::tooManyAttempts(strtolower($user->email).'|127.0.0.1', 5));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_users_logs', [
            'action' => 'user_logged_out', 'entity_id' => (string) $user->id, 'causer_id' => (string) $user->id,
        ]);
        $response->assertRedirect('/');
    }
}
