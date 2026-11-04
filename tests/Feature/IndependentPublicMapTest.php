<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\PublicationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndependentPublicMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PublicationCategory::query()->update(['is_public' => true, 'source_layer_id' => null]);
    }

    private function house(array $changes = []): House
    {
        return House::create(array_merge([
            'house_code' => 'PRIVATE-CODE-001', 'address' => 'PRIVATE-ADDRESS',
            'latitude' => -7.3574198, 'longitude' => 110.9874323,
            'verification_status' => 'verified', 'publication_status' => 'published', 'public_visible' => true,
        ], $changes));
    }

    private function facility(array $changes = []): Facility
    {
        return Facility::create(array_merge([
            'name' => 'Test Clinic', 'category' => 'Kesehatan', 'latitude' => -7.3575198,
            'longitude' => 110.9875323, 'status' => 'published',
        ], $changes));
    }

    public function test_both_objects_are_visible_without_any_layer_or_feature_and_keep_private_data_out(): void
    {
        $house = $this->house();
        $facility = $this->facility();
        $card = FamilyCard::create(['house_id' => $house->id, 'head_name' => 'PRIVATE-HEAD', 'card_number' => 'PRIVATE-KK']);
        FamilyMember::create(['family_card_id' => $card->id, 'full_name' => 'PRIVATE-MEMBER']);
        foreach (['/api/map/public', '/api/v1/map/objects'] as $url) {
            $this->getJson($url)->assertOk()->assertJsonCount(2, 'features')->assertDontSee('PRIVATE-')
                ->assertJsonPath('features.0.geometry.coordinates', [$house->longitude, $house->latitude])
                ->assertJsonPath('features.1.geometry.coordinates', [$facility->longitude, $facility->latitude]);
        }
        $this->getJson('/api/v1/map/objects/house/'.$house->id)->assertOk()->assertDontSee('PRIVATE-');
        $this->getJson('/api/v1/map/objects/facility/'.$facility->id)->assertOk();
        $this->getJson('/api/v1/map/objects/user/1')->assertNotFound();
        $this->getJson('/api/v1/map/search?q=Test')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/map/search?q=PRIVATE')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('map_layers', 0);
        $this->assertDatabaseCount('map_features', 0);
        $this->assertNull($house->map_feature_id);
    }

    public function test_house_own_status_consent_and_coordinates_control_visibility(): void
    {
        $house = $this->house();
        foreach ([['verification_status' => 'pending'], ['publication_status' => 'draft'],
            ['publication_status' => 'archived'], ['archived_at' => now()], ['public_visible' => false],
            ['latitude' => null], ['longitude' => 200], ['latitude' => 0, 'longitude' => 0]] as $changes) {
            $original = $house->getAttributes();
            $house->update($changes);
            $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
            $house->setRawAttributes($original)->save();
        }
    }

    public function test_facility_draft_withdrawn_archived_invalid_and_incomplete_remain_hidden(): void
    {
        $facility = $this->facility();
        foreach ([['status' => 'draft'], ['status' => 'archived'], ['archived_at' => now()],
            ['latitude' => 100], ['longitude' => 200], ['latitude' => 0, 'longitude' => 0], ['name' => '']] as $changes) {
            $original = $facility->getAttributes();
            $facility->update($changes);
            $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
            $facility->setRawAttributes($original)->save();
        }
    }

    public function test_parent_status_geometry_and_detachment_cannot_hide_eligible_objects(): void
    {
        $layer = MapLayer::create(['name' => 'Legacy', 'slug' => 'legacy', 'layer_type' => 'polygon', 'status' => 'archived', 'is_public' => false]);
        $feature = MapFeature::create(['layer_id' => $layer->id, 'name' => 'PRIVATE-LEGACY', 'category' => 'rumah',
            'verification_status' => 'pending', 'publication_status' => 'archived',
            'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]]]);
        $house = $this->house(['map_feature_id' => $feature->id]);
        $this->facility();
        PublicationCategory::whereKey('houses')->update(['source_layer_id' => $layer->id]);
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(2, 'features');
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertOk()
            ->assertJsonPath('geometry.coordinates', [$house->longitude, $house->latitude]);
        $this->getJson('/api/v1/map/layers/legacy/features')->assertOk()->assertJsonCount(1, 'features');
        $house->update(['map_feature_id' => null]);
        PublicationCategory::whereKey('houses')->update(['source_layer_id' => null]);
        $feature->delete();
        $layer->delete();
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(2, 'features');
    }

    public function test_legacy_category_switches_neither_hide_nor_rewrite_objects(): void
    {
        $this->house();
        $this->facility();
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin)->patchJson('/admin/publication/houses', ['is_public' => false])->assertOk();
        $this->getJson('/api/map/public')->assertJsonCount(2, 'features');
        $this->patchJson('/admin/publication/houses', ['is_public' => true])->assertOk();
        $this->getJson('/api/map/public')->assertJsonCount(2, 'features');
        $this->assertDatabaseHas('houses', ['verification_status' => 'verified', 'publication_status' => 'published']);
        $this->assertDatabaseHas('facilities', ['status' => 'published']);
    }
}
