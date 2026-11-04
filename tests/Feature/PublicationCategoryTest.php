<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\PublicationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PublicationCategoryTest extends TestCase
{
    use RefreshDatabase;

    private MapLayer $houseLayer;

    private MapLayer $facilityLayer;

    private House $house;

    private Facility $facility;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->houseLayer = MapLayer::create(['name' => 'Storage A', 'slug' => 'arbitrary-storage-a', 'layer_type' => 'point', 'status' => 'draft', 'is_public' => false]);
        $this->facilityLayer = MapLayer::create(['name' => 'Storage B', 'slug' => 'arbitrary-storage-b', 'layer_type' => 'point', 'status' => 'draft', 'is_public' => false]);
        PublicationCategory::findOrFail('houses')->update(['source_layer_id' => $this->houseLayer->id]);
        PublicationCategory::findOrFail('facilities')->update(['source_layer_id' => $this->facilityLayer->id]);
        $feature = MapFeature::create([
            'layer_id' => $this->houseLayer->id, 'name' => 'PRIVATE-HOUSE-NAME', 'category' => 'rumah',
            'geometry' => ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]],
            'latitude' => -7.3574198, 'longitude' => 110.9874323,
            'verification_status' => 'verified', 'publication_status' => 'published',
        ]);
        $this->house = House::create([
            'map_feature_id' => $feature->id, 'house_code' => 'BGN-CATEGORY-001',
            'address' => 'PRIVATE-ADDRESS', 'rt_code' => '01', 'public_visible' => true,
            'latitude' => -7.3574198, 'longitude' => 110.9874323,
            'verification_status' => 'verified', 'publication_status' => 'published',
        ]);
        $card = FamilyCard::create(['house_id' => $this->house->id, 'head_name' => 'PRIVATE-FAMILY-HEAD']);
        FamilyMember::create(['family_card_id' => $card->id, 'full_name' => 'PRIVATE-FAMILY-MEMBER']);
        $this->facility = Facility::create(['name' => 'Public Facility', 'category' => 'Pendidikan', 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'status' => 'published']);
        $this->admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function toggle(string $key, bool $value): void
    {
        $this->actingAs($this->admin)->patchJson(route('admin.publication.update', $key), ['is_public' => $value])
            ->assertOk()->assertJsonPath('is_public', $value);
        $this->assertSame($value, PublicationCategory::findOrFail($key)->is_public);
    }

    private function publicSnapshot(): array
    {
        return $this->getJson('/api/map/public')->assertOk()->json('features');
    }

    private function objectSnapshot(): array
    {
        $rows = [];
        foreach (['users', 'houses', 'map_features', 'map_layers', 'family_cards', 'family_members', 'facilities'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $rows;
    }

    public function test_management_reports_exactly_two_system_categories_without_visibility_controls_or_category_crud(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.publication.index'))->assertOk();
        $this->assertSame(0, substr_count($response->getContent(), 'type="checkbox"'));
        $response->assertSee('Rumah Warga')->assertSee('Fasilitas Umum')
            ->assertSee('Publikasi individual')->assertDontSee('Tambah kategori');
        $this->assertDatabaseCount('publication_categories', 2);
    }

    public function test_house_category_metadata_change_does_not_gate_eligible_objects(): void
    {
        $this->assertCount(2, $this->publicSnapshot());
        $this->toggle('houses', true);
        $features = $this->publicSnapshot();
        $this->assertCount(2, $features);
        $this->assertEqualsCanonicalizing(['houses', 'facilities'], collect($features)->pluck('properties.system_key')->all());
        $this->assertFalse(PublicationCategory::findOrFail('facilities')->is_public);
        $this->get(route('admin.publication.index'))->assertOk()
            ->assertSee('data-eligible-count="1"', false)->assertDontSee('type="checkbox"', false);
    }

    public function test_facility_category_metadata_change_does_not_gate_eligible_objects(): void
    {
        $this->toggle('facilities', true);
        $features = $this->publicSnapshot();
        $this->assertCount(2, $features);
        $this->assertEqualsCanonicalizing(['houses', 'facilities'], collect($features)->pluck('properties.system_key')->all());
        $this->assertFalse(PublicationCategory::findOrFail('houses')->is_public);
        $this->assertNull(AuditLog::where('action', 'category.visibility_changed')->firstOrFail()->entity_id);
    }

    public function test_toggles_do_not_mutate_objects_verification_archival_coordinates_or_relations(): void
    {
        $before = $this->objectSnapshot();
        $this->toggle('houses', true);
        $this->toggle('facilities', true);
        $this->assertCount(2, $this->publicSnapshot());
        $this->toggle('houses', false);
        $this->assertCount(2, $this->publicSnapshot());
        $this->toggle('houses', true);
        $this->toggle('facilities', false);
        $this->assertCount(2, $this->publicSnapshot());
        $this->assertSame($before, $this->objectSnapshot());
    }

    public function test_versioned_object_and_search_paths_ignore_legacy_category_status(): void
    {
        $id = $this->house->map_feature_id;
        $this->getJson('/api/v1/map/features/'.$id)->assertOk()->assertDontSee('PRIVATE-');
        $this->getJson('/api/v1/map/layers/'.$this->houseLayer->slug.'/features')->assertNotFound();
        $this->getJson('/api/v1/map/search?q=Lokasi')->assertOk()->assertJsonCount(1, 'data');
        $this->toggle('houses', true);
        $this->getJson('/api/v1/map/features/'.$id)->assertOk()->assertDontSee('PRIVATE-');
        $this->getJson('/api/v1/map/layers/'.$this->houseLayer->slug.'/features')->assertOk()->assertJsonCount(1, 'features');
        $this->getJson('/api/v1/map/search?q=Lokasi')->assertOk()->assertJsonCount(1, 'data');
        $this->toggle('houses', false);
        $this->getJson('/api/v1/map/features/'.$id)->assertOk()->assertDontSee('PRIVATE-');
        $this->getJson('/api/v1/map/layers/'.$this->houseLayer->slug.'/features')->assertNotFound();
        $this->getJson('/api/v1/map/search?q=Lokasi')->assertJsonCount(1, 'data');
    }

    public function test_public_payload_never_exposes_family_or_private_address(): void
    {
        $this->toggle('houses', true);
        $this->getJson('/api/map/public')->assertOk()->assertDontSee('PRIVATE-')
            ->assertJsonCount(2, 'features');
    }

    public function test_individual_pending_draft_missing_consent_and_archived_remain_hidden(): void
    {
        $this->toggle('houses', true);
        foreach ([
            ['verification_status' => 'pending'],
            ['publication_status' => 'draft'],
            ['public_visible' => false],
            ['archived_at' => now()],
        ] as $change) {
            $before = $this->house->getRawOriginal();
            $this->house->update($change);
            $this->assertCount(1, $this->publicSnapshot());
            DB::table('houses')->where('id', $this->house->id)->update($before);
            $this->house->refresh();
        }
        $this->house->mapFeature->update(['verification_status' => 'pending']);
        $this->assertCount(2, $this->publicSnapshot());
    }

    public function test_archived_individual_facility_is_not_restored_by_checkbox(): void
    {
        $this->facility->update(['status' => 'archived', 'archived_at' => now()]);
        $this->toggle('facilities', true);
        $this->assertCount(1, $this->publicSnapshot());
        $this->assertSame('archived', $this->facility->fresh()->status);
    }

    public function test_source_rename_does_not_change_identity_or_input_house_layer(): void
    {
        $this->houseLayer->update(['name' => 'Renamed source', 'slug' => 'renamed-source']);
        $this->toggle('houses', true);
        $this->assertCount(2, $this->publicSnapshot());
        $this->actingAs($this->admin)->post(route('admin.houses.store'), [
            'address' => 'Private input address', 'rt_code' => '01', 'latitude' => -7.3574198, 'longitude' => 110.9874323,
            'households' => [['head_of_family_name' => 'Private head', 'members' => [['full_name' => 'Private member']]]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->houseLayer->id, House::latest('id')->first()->mapFeature->layer_id);
        $this->assertDatabaseCount('map_layers', 2);
    }

    public function test_facility_input_remains_independent_and_new_object_stays_draft(): void
    {
        $this->toggle('facilities', true);
        $this->actingAs($this->admin)->post(route('admin.facilities.store'), [
            'name' => 'New input facility', 'category' => 'Pendidikan', 'latitude' => -7.3574198, 'longitude' => 110.9874323,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', Facility::latest('id')->first()->status);
        $this->assertCount(2, $this->publicSnapshot());
        $this->assertDatabaseCount('houses', 1);
    }

    public function test_rt_guest_invalid_key_and_forged_mapping_cannot_change_system_configuration(): void
    {
        $this->patchJson(route('admin.publication.update', 'houses'), ['is_public' => true])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'admin_rt', 'is_active' => true]))
            ->patchJson(route('admin.publication.update', 'houses'), ['is_public' => true])->assertForbidden();
        $this->actingAs($this->admin)->patchJson('/admin/publication/custom', ['is_public' => true])->assertNotFound();
        $this->patchJson('/admin/publication/houses', ['is_public' => false, 'source_layer_id' => $this->facilityLayer->id])->assertOk();
        $this->assertSame($this->houseLayer->id, PublicationCategory::findOrFail('houses')->source_layer_id);
    }

    public function test_system_sources_cannot_be_deleted_or_archived_and_ordinary_referenced_layer_is_protected(): void
    {
        $this->actingAs($this->admin);
        foreach ([$this->houseLayer, $this->facilityLayer] as $layer) {
            $this->deleteJson(route('admin.layers.destroy', $layer))->assertStatus(409);
        }
        $this->putJson(route('admin.layers.update', $this->houseLayer), [
            'name' => 'same', 'layer_type' => 'polygon', 'color' => '#123456', 'status' => 'draft', 'is_public' => false,
        ])->assertStatus(409);
        $this->assertDatabaseCount('map_layers', 2);
        $this->assertNotNull($this->house->fresh()->mapFeature);
    }

    public function test_archived_parent_does_not_change_independent_category_visibility(): void
    {
        $this->toggle('houses', true);
        DB::table('map_layers')->where('id', $this->houseLayer->id)->update(['status' => 'archived']);
        $this->assertCount(2, $this->publicSnapshot());
        $this->toggle('houses', false);
        $this->assertCount(2, $this->publicSnapshot());
        $this->assertSame('archived', $this->houseLayer->fresh()->status);
        $this->toggle('houses', true);
        $this->assertCount(2, $this->publicSnapshot());
        PublicationCategory::whereKey('houses')->update(['source_layer_id' => null]);
        $this->assertCount(2, $this->publicSnapshot());
    }

    public function test_public_map_has_only_two_fixed_category_filters_and_no_dynamic_layer_select(): void
    {
        $response = $this->get('/peta')->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'data-public-category='));
        $response->assertDontSee('id="map-category"', false)->assertDontSee('id="map-layers"', false);
    }

    public function test_facility_map_features_are_classified_by_mapping_and_relation_not_names(): void
    {
        MapFeature::create(['layer_id' => $this->facilityLayer->id, 'name' => 'Facility map point', 'category' => 'Pendidikan',
            'geometry' => ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]], 'verification_status' => 'verified', 'publication_status' => 'published']);
        $this->toggle('facilities', true);
        $this->assertCount(3, $this->publicSnapshot());
        $this->toggle('facilities', false);
        $this->toggle('houses', true);
        $this->assertCount(3, $this->publicSnapshot());
    }

    public function test_failed_audit_rolls_back_checkbox_and_leaves_other_category_untouched(): void
    {
        AuditLog::creating(fn () => throw new RuntimeException('Synthetic audit failure'));
        $this->actingAs($this->admin)->patchJson('/admin/publication/houses', ['is_public' => true])->assertStatus(500);
        $this->assertFalse(PublicationCategory::findOrFail('houses')->is_public);
        $this->assertFalse(PublicationCategory::findOrFail('facilities')->is_public);
    }

    public function test_ordinary_layer_with_objects_is_not_deleted_and_empty_layer_can_be_archived(): void
    {
        $layer = MapLayer::create(['name' => 'Ordinary', 'slug' => 'ordinary', 'layer_type' => 'point']);
        MapFeature::create(['layer_id' => $layer->id, 'name' => 'Existing object']);
        $this->actingAs($this->admin)->deleteJson(route('admin.layers.destroy', $layer))->assertStatus(409);
        $empty = MapLayer::create(['name' => 'Empty', 'slug' => 'empty', 'layer_type' => 'point']);
        $this->delete(route('admin.layers.destroy', $empty))->assertRedirect();
        $this->assertSame('archived', $empty->fresh()->status);
        $this->assertNotNull($layer->fresh());
    }
}
