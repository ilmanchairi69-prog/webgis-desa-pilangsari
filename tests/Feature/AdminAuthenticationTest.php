<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_super_admin_can_log_in(): void
    {
        $password = 'Test-Password-2026!';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $user->forceFill([
            'role' => 'super_admin',
            'is_active' => true,
        ])->save();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_active_admin_rt_can_log_in_to_shared_dashboard(): void
    {
        $password = 'Test-Password-2026!';
        $user = User::factory()->create(['password' => Hash::make($password)]);
        $user->forceFill(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true])->save();

        $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => $password]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $password = 'Test-Password-2026!';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $user->forceFill([
            'role' => 'super_admin',
            'is_active' => false,
        ])->save();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_rt_can_access_shared_dashboard(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => 'admin_rt',
            'is_active' => true,
        ])->save();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
    }

    public function test_admin_rt_cannot_access_super_admin_verification_area(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin_rt', 'is_active' => true, 'rt_code' => '01'])->save();
        $this->actingAs($user)->get(route('admin.verification.index'))->assertForbidden();
    }

    public function test_inactive_super_admin_is_logged_out_when_accessing_dashboard(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => 'super_admin',
            'is_active' => false,
        ])->save();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $ip = '198.51.100.77';
        $password = 'Correct-Password-2026!';

        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $user->forceFill([
            'role' => 'super_admin',
            'is_active' => true,
        ])->save();

        $emailKey = 'admin-login:' . sha1(
            mb_strtolower($user->email) . '|' . $ip
        );
        $ipKey = 'admin-login-ip:' . sha1($ip);

        RateLimiter::clear($emailKey);
        RateLimiter::clear($ipKey);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post(route('login.store'), [
                    'email' => $user->email,
                    'password' => 'Wrong-Password-2026!',
                ])
                ->assertSessionHasErrors('email');
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($emailKey, 5));
        $this->assertGuest();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => $password,
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
