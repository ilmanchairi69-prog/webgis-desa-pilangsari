<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertFalse(app()->configurationIsCached());
    }

    private function enterCredentials(string $name = 'Pengelola Uji', string $email = 'admin@example.test', string $password = 'Testing-password-123!', ?string $confirmation = null): PendingCommand
    {
        return $this->artisan('webgis:make-super-admin')
            ->expectsQuestion('Nama Super Admin', $name)
            ->expectsQuestion('Email login', $email)
            ->expectsQuestion('Kata sandi (minimal 12 karakter)', $password)
            ->expectsQuestion('Ulangi kata sandi', $confirmation ?? $password);
    }

    public function test_bootstrap_hashes_password_and_creates_an_active_super_admin_who_can_log_in(): void
    {
        $password = 'Testing-password-123!';
        $this->enterCredentials('  Pengelola Uji  ', ' ADMIN@EXAMPLE.TEST ', $password)->assertSuccessful();
        $user = User::sole();
        $this->assertSame('Pengelola Uji', $user->name);
        $this->assertSame('admin@example.test', $user->email);
        $this->assertSame('super_admin', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->rt_code);
        $this->assertNull($user->assigned_rt);
        $this->assertNotSame($password, $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check($password, $user->getRawOriginal('password')));
        $audit = AuditLog::sole();
        $this->assertSame('user.bootstrap_created', $audit->action);
        $this->assertSame($user->id, $audit->subject_id);
        $this->assertStringNotContainsString($password, $audit->toJson());
        $this->assertStringNotContainsString($user->getRawOriginal('password'), $audit->toJson());
        $this->post(route('login.store'), ['email' => $user->email, 'password' => $password])
            ->assertRedirect(route('admin.dashboard'))->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    #[DataProvider('invalidCredentials')]
    public function test_invalid_credentials_do_not_create_users_or_audit_records(string $name, string $email, string $password, string $confirmation): void
    {
        $this->enterCredentials($name, $email, $password, $confirmation)->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public static function invalidCredentials(): array
    {
        return [
            'empty name' => ['   ', 'admin@example.test', 'Testing-password-123!', 'Testing-password-123!'],
            'long name' => [str_repeat('n', 256), 'admin@example.test', 'Testing-password-123!', 'Testing-password-123!'],
            'invalid email' => ['Admin', 'invalid', 'Testing-password-123!', 'Testing-password-123!'],
            'email exceeds login limit' => ['Admin', str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 60).'.test', 'Testing-password-123!', 'Testing-password-123!'],
            'short password' => ['Admin', 'admin@example.test', 'short', 'short'],
            'multibyte short password' => ['Admin', 'admin@example.test', str_repeat('é', 6), str_repeat('é', 6)],
            'mismatched confirmation' => ['Admin', 'admin@example.test', 'Testing-password-123!', 'Different-password-123!'],
            'bcrypt byte limit' => ['Admin', 'admin@example.test', str_repeat('p', 73), str_repeat('p', 73)],
        ];
    }

    public function test_duplicate_email_does_not_overwrite_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test', 'role' => 'admin_rt']);
        $originalHash = $user->getRawOriginal('password');
        $this->enterCredentials()->expectsOutput('Email sudah digunakan.')->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame($originalHash, $user->fresh()->getRawOriginal('password'));
    }

    public function test_existing_super_admin_prevents_another_bootstrap_even_when_inactive(): void
    {
        User::factory()->create(['role' => 'super_admin', 'is_active' => false]);
        $this->artisan('webgis:make-super-admin')->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_non_interactive_execution_cannot_create_an_account(): void
    {
        $this->artisan('webgis:make-super-admin', ['--no-interaction' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_audit_failure_rolls_back_user_creation(): void
    {
        $transactionSeen = false;
        AuditLog::creating(function () use (&$transactionSeen): void {
            $transactionSeen = DB::transactionLevel() > 1;
            throw new RuntimeException('Audit write failed in isolated test');
        });
        try {
            $this->enterCredentials()->run();
            $this->fail('Expected audit failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit write failed in isolated test', $exception->getMessage());
        }
        $this->assertTrue($transactionSeen);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
