<?php

namespace Tests\Feature;

use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinalRemediationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function feature(array $overrides = []): MapFeature
    {
        $layer = MapLayer::create(['name' => 'Layer Uji', 'slug' => 'final-uji', 'layer_type' => 'point', 'is_public' => true, 'status' => 'published']);
        $this->enablePublicationFixture('facilities', $layer);

        return MapFeature::create($overrides + ['layer_id' => $layer->id, 'name' => 'Objek Uji', 'category' => 'area', 'geometry' => ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]], 'verification_status' => 'verified', 'publication_status' => 'published']);
    }

    public function test_archiving_house_synchronizes_feature_and_preserves_family(): void
    {
        $admin = $this->admin();
        $feature = $this->feature();
        $house = House::create(['map_feature_id' => $feature->id, 'house_code' => 'BGN-ARCHIVE-SYNC', 'rt_code' => '01', 'public_visible' => true, 'verification_status' => 'verified', 'publication_status' => 'published']);
        $this->enablePublicationFixture('houses', $feature->layer);
        $card = FamilyCard::create(['house_id' => $house->id, 'head_name' => 'Privat']);
        $member = FamilyMember::create(['family_card_id' => $card->id, 'full_name' => 'Anggota Privat']);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(1, 'features');
        $this->actingAs($admin)->post(route('admin.houses.archive', $house))->assertRedirect();
        $this->assertSame('archived', $house->fresh()->publication_status);
        $this->assertSame('archived', $feature->fresh()->publication_status);
        $this->assertDatabaseHas('family_cards', ['id' => $card->id]);
        $this->assertDatabaseHas('family_members', ['id' => $member->id]);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'house.archived', 'entity_id' => $house->id]);
    }

    public static function malformedGeometry(): array
    {
        $a = [110.9874323, -7.3574198];
        $b = [110.9874333, -7.3574198];
        $c = [110.9874323, -7.3574188];

        return [
            'scalar ring' => [['type' => 'Polygon', 'coordinates' => [1]]],
            'unclosed inner ring' => [['type' => 'Polygon', 'coordinates' => [[$a, $b, $c, $a], [$a, $b, $c, $b]]]],
            'unclosed multipolygon' => [['type' => 'MultiPolygon', 'coordinates' => [[[$a, $b, $c, $b]]]]],
            'short polygon' => [['type' => 'Polygon', 'coordinates' => [[$a, $b, $a]]]],
            'short line' => [['type' => 'LineString', 'coordinates' => [$a]]],
            'invalid point' => [['type' => 'Point', 'coordinates' => ['not-numeric', 10]]],
        ];
    }

    #[DataProvider('malformedGeometry')]
    public function test_malformed_geometry_returns_validation_error_without_mutating_database(array $geometry): void
    {
        $feature = $this->feature();
        $this->actingAs($this->admin())->postJson(route('admin.features.store'), ['name' => 'Invalid', 'category' => 'area', 'layer_id' => $feature->layer_id, 'geometry' => json_encode($geometry)])->assertUnprocessable()->assertJsonValidationErrors('geometry');
        $this->assertDatabaseCount('map_features', 1);
    }

    #[DataProvider('malformedGeometry')]
    public function test_legacy_invalid_geometry_is_not_served_by_any_public_endpoint(array $geometry): void
    {
        $feature = $this->feature(['geometry' => $geometry]);
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
        $this->getJson('/api/v1/map/layers/final-uji/features')->assertOk()->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/search?q=Objek')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_array_is_validation_error_and_invalid_feature_id_is_not_found(): void
    {
        $this->getJson('/api/v1/map/search?q[]=name')->assertUnprocessable();
        $this->getJson('/api/v1/map/features/not-an-id')->assertNotFound();
    }

    public function test_house_address_limit_matches_existing_mysql_column(): void
    {
        $this->actingAs($this->admin())->postJson(route('admin.houses.store'), ['address' => str_repeat('a', 501), 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'households' => [['head_of_family_name' => 'Privat']]])->assertUnprocessable()->assertJsonValidationErrors('address');
        $this->assertDatabaseCount('houses', 0);
    }

    public function test_rt_cannot_update_house_reassigned_after_route_binding(): void
    {
        $feature = $this->feature();
        $house = House::create(['map_feature_id' => $feature->id, 'house_code' => 'BGN-RACE', 'rt_code' => '01', 'address' => 'Alamat asli']);
        $this->enablePublicationFixture('houses', $feature->layer);
        $rt = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        Route::bind('house', function (string $id): House {
            $stale = House::findOrFail($id);
            DB::table('houses')->where('id', $id)->update(['rt_code' => '02']);

            return $stale;
        });
        $this->actingAs($rt)->put(route('admin.houses.update', $house), ['address' => 'Perubahan ilegal', 'latitude' => -7.3574198, 'longitude' => 110.9874323])->assertForbidden();
        $this->assertSame('Alamat asli', $house->fresh()->address);
        $this->assertSame('02', $house->fresh()->rt_code);
    }

    public function test_verification_of_inconsistent_legacy_record_does_not_publish_it(): void
    {
        $feature = $this->feature();
        $house = House::create(['map_feature_id' => $feature->id, 'house_code' => 'BGN-LEGACY-REVIEW', 'public_visible' => true, 'verification_status' => 'pending', 'publication_status' => 'published']);
        $this->enablePublicationFixture('houses', $feature->layer);
        $this->actingAs($this->admin())->post(route('admin.houses.verify', $house))->assertRedirect();
        $this->assertSame('verified', $house->fresh()->verification_status);
        $this->assertSame('draft', $house->fresh()->publication_status);
        $this->assertSame('draft', $feature->fresh()->publication_status);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
    }

    public function test_malformed_admin_filters_are_validation_errors(): void
    {
        $this->actingAs($this->admin());
        foreach (['/admin/houses?q[]=x', '/admin/map?q[]=x', '/admin/audit-logs?action[]=x', '/admin/audit-logs?from=bad-date'] as $url) {
            $this->getJson($url)->assertUnprocessable();
        }
    }

    public function test_public_layer_query_count_does_not_grow_per_feature(): void
    {
        $feature = $this->feature();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/map/layers/final-uji/features')->assertOk()->assertJsonCount(1, 'features');
        $one = count(DB::getQueryLog());
        for ($i = 0; $i < 9; $i++) {
            $copy = $feature->replicate();
            $copy->name = 'Objek '.$i;
            $copy->save();
        }
        DB::flushQueryLog();
        $this->getJson('/api/v1/map/layers/final-uji/features')->assertOk()->assertJsonCount(10, 'features');
        $this->assertSame($one, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_public_geometry_does_not_copy_private_foreign_members(): void
    {
        $feature = $this->feature(['geometry' => ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198], 'private_address' => 'Alamat geometri privat', 'family' => ['head_name' => 'Kepala Rahasia', 'card_number' => 'KK-PRIVATE']]]);
        foreach (['/api/v1/map/features/'.$feature->id, '/api/v1/map/layers/final-uji/features'] as $url) {
            $this->getJson($url)->assertOk()->assertDontSee('Alamat geometri privat')->assertDontSee('Kepala Rahasia')->assertDontSee('KK-PRIVATE')->assertDontSee('private_address');
        }
    }

    public function test_empty_legacy_geometry_can_use_valid_numeric_coordinates(): void
    {
        $feature = $this->feature(['geometry' => [], 'latitude' => -7.3574198, 'longitude' => 110.9874323]);
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertOk()->assertJsonPath('geometry.type', 'Point')->assertJsonPath('geometry.coordinates', [110.9874323, -7.3574198]);
    }
}
