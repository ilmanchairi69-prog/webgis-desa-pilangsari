<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SqaQa003UserEmailValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'name' => 'QA Admin', 'email' => 'admin@example.com', 'role' => 'super_admin', 'is_active' => true,
        ]);
    }

    public function test_new_email_is_trimmed_lowercased_and_stored(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->createPayload('  New.User@Example.COM  '))
            ->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'new.user@example.com', 'role' => 'admin_rt']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
    }

    #[DataProvider('duplicateEmails')]
    public function test_case_insensitive_duplicate_create_returns_validation_error_without_mutation(string $email): void
    {
        $before = User::count();
        $auditBefore = AuditLog::count();
        $this->actingAs($this->admin)->postJson(route('admin.users.store'), $this->createPayload($email))
            ->assertUnprocessable()->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Email sudah digunakan oleh akun lain.');
        $this->assertSame($before, User::count());
        $this->assertSame($auditBefore, AuditLog::count());
    }

    public static function duplicateEmails(): array
    {
        return [
            'identical' => ['admin@example.com'],
            'uppercase' => ['ADMIN@EXAMPLE.COM'],
            'mixed local' => ['Admin@example.com'],
            'mixed domain' => ['admin@EXAMPLE.COM'],
            'trimmed mixed' => ['  AdMiN@ExAmPlE.CoM  '],
        ];
    }

    public function test_update_accepts_own_email_and_other_field_changes(): void
    {
        $target = User::factory()->create(['email' => 'target@example.com', 'role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        $payload = $this->updatePayload($target->email, ['name' => 'Nama diperbarui']);
        $this->actingAs($this->admin)->put(route('admin.users.update', $target), $payload)
            ->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'email' => 'target@example.com', 'name' => 'Nama diperbarui']);

        $payload = $this->updatePayload(' TARGET@EXAMPLE.COM ');
        $this->put(route('admin.users.update', $target), $payload)
            ->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();
        $this->assertSame('target@example.com', $target->fresh()->email);
    }

    public function test_update_rejects_another_users_email_and_accepts_a_new_email(): void
    {
        $target = User::factory()->create(['email' => 'target@example.com', 'role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        $other = User::factory()->create(['email' => 'other@example.com', 'role' => 'admin_rt', 'rt_code' => '02', 'is_active' => true]);
        $before = User::count();

        $this->actingAs($this->admin)->putJson(route('admin.users.update', $target), $this->updatePayload('OtHeR@Example.COM'))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($before, User::count());
        $this->assertSame('target@example.com', $target->fresh()->email);
        $this->assertSame('other@example.com', $other->fresh()->email);

        $this->put(route('admin.users.update', $target), $this->updatePayload(' Brand.New@Example.COM '))
            ->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();
        $this->assertSame('brand.new@example.com', $target->fresh()->email);
    }

    public function test_invalid_email_returns_422_without_account_or_audit_changes(): void
    {
        $before = User::count();
        $auditBefore = AuditLog::count();
        $this->actingAs($this->admin)->postJson(route('admin.users.store'), $this->createPayload('bukan-email'))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($before, User::count());
        $this->assertSame($auditBefore, AuditLog::count());
    }

    private function createPayload(string $email): array
    {
        return [
            'name' => 'QA Target', 'email' => $email, 'role' => 'admin_rt', 'rt_code' => '01',
            'password' => 'Secure-QA-Password-2026', 'password_confirmation' => 'Secure-QA-Password-2026',
        ];
    }

    private function updatePayload(string $email, array $overrides = []): array
    {
        return array_merge([
            'name' => 'QA Target', 'email' => $email, 'role' => 'admin_rt', 'rt_code' => '01', 'is_active' => '1',
            'password' => '', 'password_confirmation' => '',
        ], $overrides);
    }
}
