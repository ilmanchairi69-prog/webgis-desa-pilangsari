<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\House;
use App\Models\User;
use App\Services\AuditWriter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class ReleaseSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_snapshots_do_not_duplicate_credentials_or_private_family_fields(): void
    {
        $user = User::factory()->create();
        AuditWriter::write('sqa.safe_snapshot', $user, [], [
            'role' => 'admin_rt', 'password' => 'SQA-PRIVATE-PASSWORD',
            'nested' => ['nik' => 'SQA-PRIVATE-NIK', 'card_number' => 'SQA-PRIVATE-KK', 'full_name' => 'SQA-PRIVATE-NAME'],
            '_token' => 'SQA-PRIVATE-CSRF', 'refresh_token' => 'SQA-PRIVATE-REFRESH',
            'apiKey' => 'SQA-PRIVATE-KEY', 'Authorization' => 'SQA-PRIVATE-AUTH',
            'object' => (object) ['password' => 'SQA-PRIVATE-OBJECT'],
            'properties' => ['unrestricted' => 'SQA-PRIVATE-PROPERTIES'],
            'geometry' => ['type' => 'Point', 'coordinates' => [110.98, -7.35], 'private_field' => 'SQA-PRIVATE-GEOMETRY'],
        ]);
        $audit = AuditLog::sole();
        $this->assertStringNotContainsString('SQA-PRIVATE', $audit->toJson());
        $this->assertSame('admin_rt', $audit->after_data['role']);
        $this->assertSame(['type' => 'Point', 'coordinates' => [110.98, -7.35]], $audit->after_data['geometry']);
        $this->assertArrayNotHasKey('address', AuditWriter::houseSnapshot(new House(['address' => 'SQA-PRIVATE-ADDRESS'])));
    }

    public function test_database_errors_are_logged_without_sql_or_binding_contents(): void
    {
        Log::shouldReceive('error')->once()->with('Database operation failed.', [
            'exception_type' => QueryException::class, 'sql_state' => null, 'driver_code' => null,
        ]);
        report(new QueryException('sqlite', 'insert into private_table (secret) values (?)', ['SQA-PRIVATE-BINDING'], new PDOException('SQA-PRIVATE-ERROR')));
    }

    public function test_non_debug_error_response_does_not_expose_internal_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/sqa-internal-error', fn () => throw new RuntimeException('SQA-PRIVATE-STACK-CONTENTS'));
        $this->getJson('/sqa-internal-error')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
        $this->get('/sqa-internal-error')->assertStatus(500)->assertDontSee('SQA-PRIVATE')->assertDontSee('RuntimeException');
    }

    public function test_non_debug_http_and_database_errors_never_echo_private_messages(): void
    {
        config(['app.debug' => false]);
        Route::get('/sqa-http-error', fn () => abort(503, 'SQA-PRIVATE-HTTP-MESSAGE'));
        $this->getJson('/sqa-http-error')->assertStatus(503)->assertExactJson(['message' => 'Server Error']);
        $this->get('/sqa-http-error')->assertStatus(503)->assertDontSee('SQA-PRIVATE');
        Route::get('/sqa-query-error', fn () => throw new QueryException('sqlite', 'insert private sql (?)', ['SQA-PRIVATE-BINDING'], new PDOException('SQA-PRIVATE-PDO')));
        $this->getJson('/sqa-query-error')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
    }

    public function test_sql_injection_search_input_does_not_bypass_rt_scope(): void
    {
        $user = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        House::create(['house_code' => 'BGN-SQA-PRIVATE-02', 'rt_code' => '02', 'address' => 'PRIVATE-OTHER-RT']);
        $this->actingAs($user)->get(route('admin.houses.index', ['q' => "%' OR 1=1 --"]))
            ->assertOk()->assertDontSee('BGN-SQA-PRIVATE-02')->assertDontSee('PRIVATE-OTHER-RT');
        $this->assertDatabaseCount('houses', 1);
    }
}
