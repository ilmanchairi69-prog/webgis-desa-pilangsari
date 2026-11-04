<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicationEligibilityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function house(): House
    {
        $layer = MapLayer::create(['name' => 'Rumah Uji', 'slug' => 'hardening-houses', 'layer_type' => 'point', 'status' => 'published', 'is_public' => true]);
        $this->enablePublicationFixture('houses', $layer);
        $feature = MapFeature::create(['layer_id' => $layer->id, 'name' => 'Nama privat legacy', 'category' => 'rumah', 'geometry' => ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]], 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'verification_status' => 'verified', 'publication_status' => 'draft']);

        return House::create(['map_feature_id' => $feature->id, 'house_code' => 'BGN-HARDENING-001', 'rt_code' => '01', 'address' => 'Alamat privat hardening', 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'verification_status' => 'verified', 'publication_status' => 'draft', 'public_visible' => true]);
    }

    public static function independentStorageChanges(): array
    {
        return [
            'private layer' => ['layer', ['is_public' => false]],
            'draft layer' => ['layer', ['status' => 'draft']],
            'archived layer' => ['layer', ['status' => 'archived']],
            'unverified feature' => ['feature', ['verification_status' => 'pending']],
            'archived feature' => ['feature', ['publication_status' => 'archived']],
            'invalid geometry' => ['feature', ['geometry' => ['type' => 'Point', 'coordinates' => ['invalid', 0]]]],
            'outside boundary' => ['feature', ['geometry' => ['type' => 'Point', 'coordinates' => [0, 0]], 'longitude' => 0, 'latitude' => 0]],
            'different valid house coordinates' => ['house', ['longitude' => 110.9858]],
        ];
    }

    #[DataProvider('independentStorageChanges')]
    public function test_individual_publication_preserves_storage_visibility_and_checks_object_eligibility(string $target, array $changes): void
    {
        $house = $this->house();
        $record = match ($target) {
            'layer' => $house->mapFeature->layer,
            'feature' => $house->mapFeature,
            default => $house,
        };
        $record->update($changes);
        $this->actingAs($this->admin())->post(route('admin.houses.publish', $house), ['publication_declaration' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('published', $house->fresh()->publication_status);
        $this->getJson('/api/map/public')->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.geometry.coordinates', [$house->fresh()->longitude, $house->fresh()->latitude]);
    }

    public static function invalidHouseCoordinates(): array
    {
        return [
            'missing latitude' => [['latitude' => null]],
            'missing longitude' => [['longitude' => null]],
            'outside boundary' => [['latitude' => -7, 'longitude' => 110]],
            'out of range' => [['latitude' => 91, 'longitude' => 181]],
        ];
    }

    #[DataProvider('invalidHouseCoordinates')]
    public function test_individual_publication_still_rejects_invalid_house_coordinates(array $changes): void
    {
        $house = $this->house();
        $house->update($changes);
        $this->actingAs($this->admin())->postJson(route('admin.houses.publish', $house), ['publication_declaration' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors('publication');
        $this->assertSame('draft', $house->fresh()->publication_status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'house.published']);
    }

    public function test_eligible_house_publication_is_visible_on_both_apis_and_withdrawal_is_immediate(): void
    {
        $house = $this->house();
        $this->actingAs($this->admin())->post(route('admin.houses.publish', $house), ['publication_declaration' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('published', $house->fresh()->publication_status);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.geometry.coordinates', [110.9874323, -7.3574198])
            ->assertDontSee('Nama privat legacy')->assertDontSee('Alamat privat hardening');
        $this->getJson('/api/v1/map/layers/hardening-houses/features')->assertOk()->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.name', 'Lokasi rumah warga terverifikasi')->assertDontSee('Nama privat legacy');
        $this->post(route('admin.houses.unpublish', $house))->assertRedirect();
        $this->getJson('/api/map/public')->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/layers/hardening-houses/features')->assertJsonCount(0, 'features');
    }

    public function test_published_house_has_a_reachable_withdrawal_form_on_the_super_admin_publication_screen(): void
    {
        $house = $this->house();
        $house->update(['publication_status' => 'published']);
        $house->mapFeature->update(['publication_status' => 'published']);
        $this->actingAs($this->admin())->get(route('admin.verification.index'))
            ->assertOk()
            ->assertSee($house->house_code)
            ->assertSee('action="'.route('admin.houses.unpublish', $house).'"', false)
            ->assertSee('Tarik publikasi');
        $this->post(route('admin.houses.unpublish', $house))->assertRedirect();
        $this->assertSame('draft', $house->fresh()->publication_status);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
        $house->update(['archived_at' => now(), 'publication_status' => 'archived']);
        $this->get(route('admin.verification.index'))->assertOk()->assertDontSee($house->house_code);
    }

    public function test_public_house_apis_ignore_legacy_layer_geometry_type(): void
    {
        $house = $this->house();
        $house->update(['publication_status' => 'published']);
        $house->mapFeature->update(['publication_status' => 'published']);
        $house->mapFeature->layer->update(['layer_type' => 'polygon']);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(1, 'features');
        $this->getJson('/api/v1/map/layers/hardening-houses/features')->assertOk()->assertJsonCount(1, 'features');
        $this->getJson('/api/v1/map/objects/house/'.$house->id)->assertOk()
            ->assertJsonPath('geometry.coordinates', [110.9874323, -7.3574198]);
    }

    public function test_public_apis_hide_previously_published_locations_outside_the_reference_boundary(): void
    {
        $house = $this->house();
        $house->update(['publication_status' => 'published', 'latitude' => -7, 'longitude' => 110]);
        $house->mapFeature->update(['publication_status' => 'published', 'geometry' => ['type' => 'Point', 'coordinates' => [110, -7]]]);
        Facility::create(['name' => 'Fasilitas luar batas', 'category' => 'Pendidikan', 'latitude' => -7, 'longitude' => 110, 'status' => 'published']);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/layers/hardening-houses/features')->assertOk()->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/objects/house/'.$house->id)->assertNotFound();
        $this->getJson('/api/v1/map/search?q=Lokasi')->assertOk()->assertJsonCount(0, 'data');
    }

    public static function invalidLayerGeometry(): array
    {
        return [
            'polygon missing geometry' => ['polygon', null],
            'line missing geometry' => ['line', null],
            'point on polygon' => ['polygon', ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]]],
            'line on polygon' => ['polygon', ['type' => 'LineString', 'coordinates' => [[110.9874323, -7.3574198], [110.9874333, -7.3574198]]]],
            'point on line' => ['line', ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]]],
        ];
    }

    #[DataProvider('invalidLayerGeometry')]
    public function test_feature_input_rejects_missing_or_mismatched_non_point_geometry(string $type, ?array $geometry): void
    {
        $layer = MapLayer::create(['name' => 'Layer Uji', 'slug' => 'geometry-type', 'layer_type' => $type, 'status' => 'draft', 'is_public' => false]);
        $this->enablePublicationFixture('houses', $layer);
        $payload = ['name' => 'Objek Uji', 'category' => 'area', 'layer_id' => $layer->id];
        if ($geometry !== null) {
            $payload['geometry'] = json_encode($geometry);
        }
        $this->actingAs($this->admin())->postJson(route('admin.features.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('geometry');
        $this->assertDatabaseCount('map_features', 0);
    }

    public function test_household_name_longer_than_actual_shared_column_is_rejected_before_database_write(): void
    {
        $this->actingAs($this->admin())->postJson(route('admin.houses.store'), ['address' => 'Alamat privat', 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'households' => [['head_of_family_name' => str_repeat('n', 161)]]])
            ->assertUnprocessable()->assertJsonValidationErrors('households.0.head_of_family_name');
        $this->assertDatabaseCount('houses', 0);
        $this->assertDatabaseCount('family_cards', 0);
    }

    public function test_facility_publication_revalidates_required_persisted_details(): void
    {
        $facility = Facility::create(['name' => '', 'category' => '', 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'status' => 'draft']);
        $this->actingAs($this->admin())->postJson(route('admin.facilities.publish', $facility))
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'category']);
        $this->assertSame('draft', $facility->fresh()->status);
    }

    public function test_house_change_audit_does_not_duplicate_private_addresses(): void
    {
        $house = $this->house();
        $this->actingAs($this->admin())->put(route('admin.houses.update', $house), ['address' => 'Alamat baru sangat privat', 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'public_visible' => '0'])->assertRedirect();
        $audit = AuditLog::where('action', 'house.updated')->sole();
        $this->assertStringNotContainsString('Alamat privat hardening', $audit->toJson());
        $this->assertStringNotContainsString('Alamat baru sangat privat', $audit->toJson());
        $this->assertTrue($audit->changes['before']['address_changed']);
    }
}
