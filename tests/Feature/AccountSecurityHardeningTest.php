<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UserController;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserAccountMutation;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccountSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertFalse(app()->configurationIsCached());
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function accountInput(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Pengelola Uji', 'email' => 'rt-test@example.test', 'role' => 'admin_rt', 'rt_code' => '01',
            'password' => 'Test-account-password!', 'password_confirmation' => 'Test-account-password!', 'is_active' => true,
        ], $overrides);
    }

    public function test_login_normalizes_email_and_does_not_accept_bcrypt_suffixes(): void
    {
        $password = str_repeat('p', 72);
        $user = User::factory()->create(['email' => 'normalized@example.test', 'password' => Hash::make($password), 'role' => 'super_admin']);
        $this->post(route('login.store'), ['email' => strtoupper($user->email), 'password' => $password])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => $password.'suffix'])->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_failed_login_audit_rolls_back_last_login_and_clears_authentication(): void
    {
        $password = 'Test-account-password!';
        $user = User::factory()->create(['password' => Hash::make($password), 'role' => 'super_admin']);
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit unavailable in isolated test');
        });
        $this->post(route('login.store'), ['email' => $user->email, 'password' => $password])->assertStatus(500);
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_logout_clears_authentication_even_when_audit_fails(): void
    {
        $this->actingAs($this->admin());
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit unavailable in isolated test');
        });
        $this->post(route('logout'))->assertStatus(500);
        $this->assertGuest();
    }

    public function test_rejected_self_demotion_never_flashes_password_inputs(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->from(route('admin.users.edit', $admin))
            ->put(route('admin.users.update', $admin), $this->accountInput(['email' => $admin->email]))
            ->assertSessionHasErrors('role')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    public function test_account_creation_rejects_password_exceeding_bcrypt_byte_limit(): void
    {
        $admin = $this->admin();
        $password = str_repeat('p', 73);
        $this->actingAs($admin)->post(route('admin.users.store'), $this->accountInput([
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_assignment_changes_sync_legacy_column_without_backfilling_unrelated_saves(): void
    {
        $admin = $this->admin();
        $rt = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01']);
        $this->assertSame('01', $rt->assigned_rt);
        $this->actingAs($admin)->put(route('admin.users.update', $rt), $this->accountInput([
            'email' => $rt->email, 'rt_code' => '02', 'password' => null, 'password_confirmation' => null,
        ]))->assertSessionHasNoErrors();
        $this->assertSame('02', $rt->fresh()->rt_code);
        $this->assertSame('02', $rt->fresh()->assigned_rt);
        DB::table('users')->where('id', $rt->id)->update(['assigned_rt' => 'legacy']);
        $rt = $rt->fresh();
        $rt->update(['name' => 'Updated name only']);
        $this->assertSame('legacy', $rt->fresh()->assigned_rt);
    }

    public function test_account_audit_failure_rolls_back_the_account_mutation(): void
    {
        $admin = $this->admin();
        $rt = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01']);
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit unavailable in isolated test');
        });
        $this->actingAs($admin)->put(route('admin.users.update', $rt), $this->accountInput([
            'email' => $rt->email, 'rt_code' => '02',
        ]))->assertStatus(500);
        $this->assertSame('01', $rt->fresh()->rt_code);
        $this->assertSame('01', $rt->fresh()->assigned_rt);
    }

    public function test_mutation_rechecks_a_stale_actor_after_account_lock(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create(['role' => 'admin_rt']);
        DB::table('users')->where('id', $actor->id)->update(['role' => 'admin_rt']);
        $request = Request::create('/admin/users/'.$target->id.'/deactivate', 'POST');
        $request->setUserResolver(fn (): User => $actor);
        try {
            app(UserController::class)->deactivate($request, $target);
            $this->fail('Stale actor must be rejected');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_rt_cannot_escalate_privileges_through_account_requests(): void
    {
        $rt = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        $this->actingAs($rt)->post(route('admin.users.store'), $this->accountInput(['role' => 'super_admin']))->assertForbidden();
        $this->actingAs($rt)->put(route('admin.users.update', $rt), $this->accountInput(['role' => 'super_admin', 'email' => $rt->email]))->assertForbidden();
        $this->assertTrue($rt->fresh()->isRtAdmin());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_mysql_lock_timeout_fails_closed_before_transaction(): void
    {
        $manager = DB::getFacadeRoot();
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->once()->andReturn('isolated_test');
        $lockName = 'webgis:accounts:'.sha1('isolated_test');
        $connection->shouldReceive('selectOne')->with('SELECT GET_LOCK(?, 5) AS acquired', [$lockName], false)->once()->andReturn((object) ['acquired' => 0]);
        DB::shouldReceive('connection')->once()->andReturn($connection);
        try {
            UserAccountMutation::run(function (): void {
                $this->fail('Mutation must not run without lock');
            });
            $this->fail('Expected lock validation error');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account', $exception->errors());
        } finally {
            DB::swap($manager);
        }
    }

    public function test_mysql_account_lock_is_released_when_transaction_throws(): void
    {
        $manager = DB::getFacadeRoot();
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->once()->andReturn('isolated_test');
        $lockName = 'webgis:accounts:'.sha1('isolated_test');
        $connection->shouldReceive('selectOne')->with('SELECT GET_LOCK(?, 5) AS acquired', [$lockName], false)->once()->andReturn((object) ['acquired' => 1]);
        $connection->shouldReceive('transaction')->once()->andThrow(new RuntimeException('Isolated transaction failure'));
        $connection->shouldReceive('selectOne')->with('SELECT RELEASE_LOCK(?) AS released', [$lockName], false)->once()->andReturn((object) ['released' => 1]);
        DB::shouldReceive('connection')->once()->andReturn($connection);
        try {
            UserAccountMutation::run(fn (): bool => true);
            $this->fail('Expected transaction failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Isolated transaction failure', $exception->getMessage());
        } finally {
            DB::swap($manager);
        }
    }
}
