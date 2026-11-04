<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapLayer;
use App\Models\PublicationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class IndependentHouseInputTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'address' => 'PRIVATE TEST ADDRESS', 'rt_code' => '99',
            'latitude' => -7.3574198, 'longitude' => 110.9874323, 'public_visible' => true,
            'households' => [['head_of_family_name' => 'PRIVATE TEST HEAD',
                'members' => [['full_name' => 'PRIVATE TEST MEMBER']]]],
        ];
    }

    public static function unavailableSources(): array
    {
        return [['missing'], ['archived'], ['polygon']];
    }

    #[DataProvider('unavailableSources')]
    public function test_house_and_family_input_does_not_require_a_usable_source_layer(string $source): void
    {
        $actor = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        $layer = null;
        if ($source !== 'missing') {
            $layer = MapLayer::create(['name' => 'Reference to preserve', 'slug' => 'reference-to-preserve',
                'layer_type' => $source === 'polygon' ? 'polygon' : 'point', 'status' => $source === 'archived' ? 'archived' : 'draft']);
            PublicationCategory::whereKey('houses')->update(['source_layer_id' => $layer->id]);
        }
        $before = $layer?->fresh()->getRawOriginal();
        $this->actingAs($actor)->post(route('admin.houses.store'), $this->payload())
            ->assertRedirect(route('admin.houses.index'))->assertSessionHasNoErrors();
        $house = House::sole();
        $this->assertNull($house->map_feature_id);
        $this->assertSame('01', $house->rt_code);
        $this->assertSame(-7.3574198, $house->latitude);
        $this->assertSame(110.9874323, $house->longitude);
        $this->assertSame('pending', $house->verification_status);
        $this->assertSame('draft', $house->publication_status);
        $this->assertDatabaseCount('map_features', 0);
        $this->assertDatabaseCount('family_cards', 1);
        $this->assertDatabaseCount('family_members', 1);
        $this->assertSame($house->id, $house->familyCards->sole()->members->sole()->house_id);
        if ($layer) {
            $this->assertSame($before, $layer->fresh()->getRawOriginal());
        } else {
            $this->assertDatabaseCount('map_layers', 0);
        }
    }

    public function test_compatible_existing_layer_keeps_optional_feature_coordinate_sync(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $layer = MapLayer::create(['name' => 'Legacy input storage', 'slug' => 'legacy-input-storage', 'layer_type' => 'point', 'status' => 'draft']);
        PublicationCategory::whereKey('houses')->update(['source_layer_id' => $layer->id]);
        $this->actingAs($actor)->post(route('admin.houses.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $house = House::sole();
        $this->assertNotNull($house->map_feature_id);
        $this->assertSame($layer->id, $house->mapFeature->layer_id);
        $this->assertSame([$house->longitude, $house->latitude], $house->mapFeature->geometry['coordinates']);
    }

    public function test_without_gis_relations_coordinate_boundary_validation_remains_required(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $payload = $this->payload();
        $payload['longitude'] = 110;
        $payload['latitude'] = -7;
        $this->actingAs($actor)->postJson(route('admin.houses.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->assertDatabaseCount('houses', 0);
        $this->assertDatabaseCount('family_cards', 0);
        $this->assertDatabaseCount('map_features', 0);
    }

    public function test_family_failure_rolls_back_layerless_house_input(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        FamilyMember::creating(fn () => throw new RuntimeException('Synthetic member write failure'));
        $this->actingAs($actor)->postJson(route('admin.houses.store'), $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('houses', 0);
        $this->assertDatabaseCount('family_cards', 0);
        $this->assertDatabaseCount('family_members', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_layerless_house_can_be_edited_and_archived_without_losing_family_data(): void
    {
        $actor = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        $this->actingAs($actor)->post(route('admin.houses.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $house = House::sole();
        $this->put(route('admin.houses.update', $house), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($house->fresh()->map_feature_id);
        $this->post(route('admin.houses.archive', $house))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($house->fresh()->archived_at);
        $this->assertSame('archived', $house->fresh()->publication_status);
        $this->assertDatabaseCount('family_cards', 1);
        $this->assertDatabaseCount('family_members', 1);
        $this->assertDatabaseCount('map_features', 0);
    }
}
