<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge(['submission_key' => (string) Str::uuid(), 'name' => 'Pelapor', 'phone' => '081234567890', 'category' => 'infrastructure', 'description' => 'Jalan membutuhkan perbaikan.', 'cf-turnstile-response' => 'valid'], $overrides);
    }

    private function configureTurnstile(bool $success = true): void
    {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $success])]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'rt_code' => $role === 'admin_rt' ? '01' : null])->save();

        return $user;
    }

    public function test_submission_is_validated_saved_privately_and_idempotent(): void
    {
        Storage::fake('local');
        $this->configureTurnstile();
        $data = $this->payload(['attachment' => UploadedFile::fake()->image('photo.jpg')]);
        $response = $this->postJson(route('complaints.store'), $data)->assertCreated()->assertJsonStructure(['ticket', 'next_key']);
        $complaint = Complaint::firstOrFail();
        Storage::disk('local')->assertExists($complaint->attachment_path);
        $this->assertStringStartsWith('complaints/', $complaint->attachment_path);
        $this->postJson(route('complaints.store'), $data)->assertOk()->assertJsonPath('ticket', $response->json('ticket'));
        $this->assertDatabaseCount('complaints', 1);
        $this->postJson(route('complaints.store'), $this->payload(['phone' => 'invalid', 'category' => 'invalid', 'attachment' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')]))->assertUnprocessable()->assertJsonValidationErrors(['phone', 'category', 'attachment']);
    }

    public function test_turnstile_fails_closed_and_submissions_are_rate_limited(): void
    {
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret' => null]);
        $this->postJson(route('complaints.store'), $this->payload())->assertStatus(503);
        $this->configureTurnstile(false);
        $this->postJson(route('complaints.store'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('cf-turnstile-response');
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('complaints.store'), $this->payload())->assertUnprocessable();
        }
        $this->postJson(route('complaints.store'), $this->payload())->assertTooManyRequests();
        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_missing_token_and_unavailable_verification_do_not_create_complaints(): void
    {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret' => 'secret']);
        $this->postJson(route('complaints.store'), $this->payload(['cf-turnstile-response' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('cf-turnstile-response');
        Http::fake(['challenges.cloudflare.com/*' => Http::response([], 503)]);
        $this->postJson(route('complaints.store'), $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_only_super_admin_can_manage_and_download_complaints(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('complaints/private.pdf', 'private');
        $complaint = Complaint::factory()->create(['attachment_path' => 'complaints/private.pdf']);
        $admin = $this->userWithRole('admin_rt');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Pengaduan Masyarakat');
        $this->get(route('admin.complaints.index'))->assertForbidden();
        $this->get(route('admin.complaints.show', $complaint))->assertForbidden();
        $this->get(route('admin.complaints.download', $complaint))->assertForbidden();
        $this->patch(route('admin.complaints.update', $complaint), ['status' => 'resolved'])->assertForbidden();
        $super = $this->userWithRole('super_admin');
        $this->actingAs($super)->get(route('admin.complaints.index', ['search' => $complaint->ticket_number, 'status' => 'new']))->assertOk()->assertSee($complaint->ticket_number);
        $this->get(route('admin.complaints.show', $complaint))->assertOk()->assertSee($complaint->description);
        $this->get(route('admin.complaints.download', $complaint))->assertOk();
        $this->patch(route('admin.complaints.update', $complaint), ['status' => 'resolved', 'admin_response' => 'Ditangani'])->assertRedirect();
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'resolved', 'responded_by' => $super->id, 'admin_response' => 'Ditangani']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.updated', 'user_id' => $super->id]);
    }
}
