<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Facility;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\PublicationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReleasePublicationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        /** @var User $admin */
        $admin = User::factory()->createOne(['role' => 'super_admin', 'is_active' => true]);

        return $admin;
    }

    private function payload(): array
    {
        return ['name' => 'Sekolah Uji', 'category' => 'Pendidikan', 'latitude' => -7.3574198, 'longitude' => 110.9874323];
    }

    private function facility(): Facility
    {
        $layer = MapLayer::firstOrCreate(['slug' => 'fixture-facilities'], ['name' => 'Facility storage', 'layer_type' => 'point']);
        $this->enablePublicationFixture('facilities', $layer);

        return Facility::create($this->payload() + ['status' => 'draft']);
    }

    public function test_facility_lifecycle_withdraws_public_data_immediately_and_writes_audit(): void
    {
        $facility = $this->facility();
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
        $this->actingAs($this->admin());
        $this->post(route('admin.facilities.publish', $facility))->assertRedirect();
        $this->assertDatabaseHas('facilities', ['id' => $facility->id, 'status' => 'published']);
        $published = $this->getJson('/api/map/public')->assertOk()->assertJsonCount(1, 'features');
        $this->assertStringContainsString('no-store', $published->headers->get('Cache-Control'));
        $this->post(route('admin.facilities.unpublish', $facility))->assertRedirect();
        $withdrawn = $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
        $this->assertStringContainsString('no-store', $withdrawn->headers->get('Cache-Control'));
        $this->post(route('admin.facilities.publish', $facility))->assertRedirect();
        $this->post(route('admin.facilities.archive', $facility))->assertRedirect();
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
        $this->post(route('admin.facilities.publish', $facility))->assertNotFound();
        foreach (['published', 'unpublished', 'archived'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => 'facility.'.$action, 'subject_id' => $facility->id]);
        }
        $audit = AuditLog::where('action', 'facility.unpublished')->firstOrFail();
        $this->assertSame('published', $audit->before_data['status']);
        $this->assertSame('draft', $audit->after_data['status']);
    }

    public function test_editing_facility_requires_review_and_ignores_forged_status(): void
    {
        $facility = $this->facility();
        $this->actingAs($this->admin());
        $this->post(route('admin.facilities.publish', $facility))->assertRedirect();
        $this->put(route('admin.facilities.update', $facility), $this->payload() + ['status' => 'published', 'created_by' => 999])->assertRedirect();
        $this->assertSame('draft', $facility->fresh()->status);
        $this->assertNull($facility->fresh()->created_by);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
        $this->assertDatabaseHas('audit_logs', ['action' => 'facility.updated', 'subject_id' => $facility->id]);
    }

    public function test_legacy_facility_outside_boundary_cannot_be_published(): void
    {
        $facility = $this->facility();
        $facility->update(['latitude' => 0, 'longitude' => 0]);
        $this->actingAs($this->admin())->post(route('admin.facilities.publish', $facility))->assertSessionHasErrors('latitude');
        $this->assertSame('draft', $facility->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'facility.published']);
    }

    private function feature(): MapFeature
    {
        $layer = MapLayer::create(['name' => 'Layer Uji', 'slug' => 'uji', 'layer_type' => 'point', 'is_public' => true, 'status' => 'published']);
        $this->enablePublicationFixture('facilities', $layer);

        return MapFeature::create(['layer_id' => $layer->id, 'name' => 'Objek Uji', 'category' => 'pendidikan', 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'verification_status' => 'verified', 'publication_status' => 'published']);
    }

    public static function legacyCategoryAndIndividualStates(): array
    {
        return [
            'legacy category disabled, object published' => [false, 'published', true],
            'legacy category disabled, object draft' => [false, 'draft', false],
            'legacy category enabled, object draft' => [true, 'draft', false],
            'legacy category enabled, object archived' => [true, 'archived', false],
        ];
    }

    #[DataProvider('legacyCategoryAndIndividualStates')]
    public function test_versioned_object_endpoints_ignore_legacy_category_and_honor_individual_status(bool $isPublic, string $publication, bool $expectedVisible): void
    {
        $feature = $this->feature();
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertOk();
        PublicationCategory::findOrFail('facilities')->update(['is_public' => $isPublic]);
        $feature->update(['publication_status' => $publication]);
        if ($expectedVisible) {
            $this->getJson('/api/v1/map/features/'.$feature->id)->assertOk();
            $this->getJson('/api/v1/map/search?q=Objek')->assertOk()->assertJsonCount(1, 'data');
            $isPublic
                ? $this->getJson('/api/v1/map/layers/uji/features')->assertOk()->assertJsonCount(1, 'features')
                : $this->getJson('/api/v1/map/layers/uji/features')->assertNotFound();

            return;
        }

        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
        $this->getJson('/api/v1/map/search?q=Objek')->assertOk()->assertJsonCount(0, 'data');
        $isPublic
            ? $this->getJson('/api/v1/map/layers/uji/features')->assertOk()->assertJsonCount(0, 'features')
            : $this->getJson('/api/v1/map/layers/uji/features')->assertNotFound();
    }

    public function test_editing_text_of_published_feature_resets_status_and_logs_change(): void
    {
        $feature = $this->feature();
        $this->actingAs($this->admin())->put(route('admin.features.update', $feature), [
            'layer_id' => $feature->layer_id, 'name' => 'Nama baru', 'category' => 'pendidikan',
            'latitude' => $feature->latitude, 'longitude' => $feature->longitude,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', $feature->fresh()->publication_status);
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'feature.updated', 'subject_id' => $feature->id]);
    }

    public function test_related_house_cannot_bypass_consent_by_changing_feature_category(): void
    {
        $feature = $this->feature();
        $house = House::create(['map_feature_id' => $feature->id, 'house_code' => 'BGN-PRIVATE', 'address' => 'Alamat rahasia', 'verification_status' => 'verified', 'publication_status' => 'published', 'public_visible' => false]);
        $this->enablePublicationFixture('houses', $feature->layer);
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
        $this->getJson('/api/v1/map/search?q=Objek')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/map/layers/uji/features')->assertOk()->assertJsonCount(0, 'features');
        $house->update(['public_visible' => true]);
        $feature->update(['name' => 'Nama kepala rahasia']);
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertOk()->assertDontSee('Nama kepala rahasia')->assertDontSee('Alamat rahasia');
        $this->getJson('/api/v1/map/search?q=Nama')->assertOk()->assertJsonCount(0, 'data')->assertDontSee('Nama kepala rahasia');
        $this->actingAs($this->admin())->put(route('admin.features.update', $feature), [])->assertStatus(409);
    }

    public function test_layer_controls_are_super_admin_only_and_do_not_withdraw_individually_published_features(): void
    {
        $feature = $this->feature();
        $this->get(route('admin.layers.index'))->assertRedirect(route('login'));
        /** @var User $rt */
        $rt = User::factory()->createOne(['role' => 'admin_rt', 'is_active' => true, 'rt_code' => '01']);
        $this->actingAs($rt)->get(route('admin.layers.index'))->assertForbidden();
        $this->put(route('admin.layers.update', $feature->layer), [])->assertForbidden();
        $this->post(route('admin.facilities.publish', $this->facility()))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.layers.index'))->assertOk();
        $this->put(route('admin.layers.update', $feature->layer), ['name' => 'Layer Uji', 'layer_type' => 'point', 'color' => '#166534', 'status' => 'draft', 'is_public' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->patchJson(route('admin.publication.update', 'facilities'), ['is_public' => false])->assertOk();
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertOk();
        $this->assertSame('published', $feature->fresh()->publication_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'layer.updated', 'subject_id' => $feature->layer_id]);
    }

    public function test_rt_without_assignment_cannot_see_other_legacy_house_on_dashboard(): void
    {
        $feature = $this->feature();
        House::create(['map_feature_id' => $feature->id, 'house_code' => 'BGN-OTHER-LEGACY', 'created_by' => $this->admin()->id]);
        /** @var User $rt */
        $rt = User::factory()->createOne(['role' => 'admin_rt', 'is_active' => true, 'rt_code' => null]);
        $this->actingAs($rt)->get(route('admin.dashboard'))->assertOk()->assertDontSee('BGN-OTHER-LEGACY')->assertViewHas('stats', fn (array $stats) => $stats['houses'] === 0);
    }

    public function test_failed_audit_insert_rolls_back_facility_publication(): void
    {
        $facility = $this->facility();
        $this->actingAs($this->admin());
        DB::unprepared("CREATE TRIGGER reject_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->post(route('admin.facilities.publish', $facility))->assertStatus(500);
        $this->assertSame('draft', $facility->fresh()->status);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
    }

    public function test_guest_cannot_access_any_admin_data_route(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'admin.')) {
                continue;
            }
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $this->call($route->methods()[0], '/'.$uri)->assertRedirect(route('login'));
        }
    }

    public function test_super_admin_screens_render_with_existing_records(): void
    {
        $admin = $this->admin();
        $feature = $this->feature();
        $house = House::create(['map_feature_id' => $feature->id, 'house_code' => 'BGN-SCREEN-AUDIT', 'created_by' => $admin->id]);
        $this->enablePublicationFixture('houses', $feature->layer);
        $facility = $this->facility();
        Storage::disk('local')->put('complaints/private.pdf', 'private');
        $complaint = Complaint::factory()->create(['attachment_path' => 'complaints/private.pdf']);
        $ids = ['house' => $house->id, 'feature' => $feature->id, 'facility' => $facility->id, 'complaint' => $complaint->id, 'layer' => $feature->layer_id, 'user' => $admin->id];
        $this->actingAs($admin);
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'admin.') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $parameters = array_intersect_key($ids, array_flip($route->parameterNames()));
            $this->get(route($route->getName(), $parameters))->assertOk();
        }
    }

    public function test_house_form_optional_member_fields_are_persisted_privately(): void
    {
        $layer = MapLayer::create(['name' => 'House storage', 'slug' => 'house-input-fixture', 'layer_type' => 'point']);
        $this->enablePublicationFixture('houses', $layer);
        /** @var User $rt */
        $rt = User::factory()->createOne(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        $member = ['full_name' => 'Anggota Privat', 'relationship' => 'Anak', 'date_of_birth' => '2010-01-02', 'gender' => 'Perempuan'];
        $payload = ['address' => 'Alamat Privat', 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'households' => [['head_of_family_name' => 'Kepala Privat', 'members' => [$member]]]];
        $this->actingAs($rt)->post(route('admin.houses.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('family_members', array_diff_key($member, ['date_of_birth' => true]));
        $this->assertSame('2010-01-02', FamilyMember::firstOrFail()->date_of_birth->toDateString());
        $this->assertFalse(House::firstOrFail()->public_visible);
        $this->getJson('/api/map/public')->assertOk()->assertDontSee('Anggota Privat')->assertDontSee('2010-01-02')->assertDontSee('Alamat Privat');
        $payload['households'][0]['members'][0]['date_of_birth'] = now()->addYear()->toDateString();
        $this->post(route('admin.houses.store'), $payload)->assertSessionHasErrors('households.0.members.0.date_of_birth');
        $this->assertDatabaseCount('houses', 1);
    }

    public function test_unchecked_house_consent_can_be_saved_and_withdraws_publication(): void
    {
        $feature = $this->feature();
        $house = House::create(['map_feature_id' => $feature->id, 'house_code' => 'BGN-CONSENT', 'rt_code' => '01', 'public_visible' => true, 'verification_status' => 'verified', 'publication_status' => 'published']);
        $this->enablePublicationFixture('houses', $feature->layer);
        /** @var User $rt */
        $rt = User::factory()->createOne(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        $this->actingAs($rt)->put(route('admin.houses.update', $house), ['address' => 'Alamat privat', 'latitude' => -7.3574198, 'longitude' => 110.9874323])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($house->fresh()->public_visible);
        $this->assertSame('draft', $house->fresh()->publication_status);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
    }

    public function test_first_super_admin_command_creates_audit_without_logging_password(): void
    {
        $this->artisan('webgis:make-super-admin')
            ->expectsQuestion('Nama Super Admin', 'Pengelola Uji')
            ->expectsQuestion('Email login', 'bootstrap@example.test')
            ->expectsQuestion('Kata sandi (minimal 12 karakter)', 'Private-test-password-2026')
            ->expectsQuestion('Ulangi kata sandi', 'Private-test-password-2026')
            ->assertSuccessful();
        $user = User::where('email', 'bootstrap@example.test')->firstOrFail();
        $audit = AuditLog::where('action', 'user.bootstrap_created')->firstOrFail();
        $this->assertSame($user->id, $audit->subject_id);
        $this->assertArrayNotHasKey('password', $audit->after_data);
        $this->assertStringNotContainsString('Private-test-password-2026', $audit->toJson());
    }
}
