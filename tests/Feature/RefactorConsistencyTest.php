<?php

namespace Tests\Feature;

use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapLayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefactorConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_legacy_and_canonical_head_updates_stay_synchronized(): void
    {
        $house = House::create(['house_code' => 'TEST-HEAD']);
        $card = FamilyCard::create(['house_id' => $house->id, 'head_of_family_name' => 'FIRST']);
        $card->update(['head_of_family_name' => 'SECOND']);
        $this->assertSame('SECOND', $card->fresh()->head_name);
        $card->update(['head_name' => 'THIRD']);
        $this->assertSame('THIRD', $card->fresh()->head_of_family_name);
    }

    public function test_member_house_is_derived_from_card_and_conflicts_are_rejected(): void
    {
        $house = House::create(['house_code' => 'TEST-PARENT']);
        $other = House::create(['house_code' => 'TEST-OTHER']);
        $card = FamilyCard::create(['house_id' => $house->id, 'head_name' => 'TEST']);
        $member = FamilyMember::create(['family_card_id' => $card->id, 'full_name' => 'TEST']);
        $this->assertSame($house->id, $member->fresh()->house_id);
        $this->expectException(ValidationException::class);
        $member->update(['house_id' => $other->id]);
    }

    public function test_rt_scope_fails_closed_and_keeps_super_admin_access(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $rt = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01']);
        $own = House::create(['house_code' => 'TEST-OWN', 'rt_code' => '01']);
        House::create(['house_code' => 'TEST-HIDDEN', 'rt_code' => '02']);
        $this->assertSame([$own->id], House::accessibleTo($rt)->pluck('id')->all());
        $this->assertSame(2, House::accessibleTo($admin)->count());
        $rt->update(['rt_code' => null]);
        $this->assertSame(0, House::accessibleTo($rt)->count());
    }

    public function test_managed_line_geometry_is_rejected_without_altering_reference_boundary(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $layer = MapLayer::create(['name' => 'Legacy Reference', 'slug' => 'legacy-reference', 'layer_type' => 'line', 'status' => 'draft']);
        $path = public_path('data/Pilangsari_Referensi.geojson');
        $before = hash_file('sha256', $path);
        $this->actingAs($admin)->postJson(route('admin.features.store'), [
            'layer_id' => $layer->id, 'name' => 'TEST-LINE',
            'geometry' => json_encode(['type' => 'LineString', 'coordinates' => [[110.9874323, -7.3574198], [110.9874324, -7.3574198]]]),
        ])->assertUnprocessable()->assertJsonValidationErrors('geometry');
        $this->assertDatabaseCount('map_features', 0);
        $this->assertSame($before, hash_file('sha256', $path));
        $this->actingAs($admin)->get(route('admin.features.create'))->assertOk()->assertSee('house-picker-map')->assertDontSee('leaflet-draw');
    }
}
