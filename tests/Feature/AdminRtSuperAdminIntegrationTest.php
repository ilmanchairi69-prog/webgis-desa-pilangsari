<?php

namespace Tests\Feature;

use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRtSuperAdminIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $rt = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'rt_code' => $rt,
            'is_active' => true,
        ]);
    }

    private function house(User $creator, string $rt, string $code): House
    {
        $layer = MapLayer::firstOrCreate(
            ['slug' => 'rumah-warga-desa-pilangsari'],
            ['name' => 'Rumah Warga', 'layer_type' => 'point', 'color' => '#166534', 'is_public' => false, 'status' => 'draft']
        );
        $this->enablePublicationFixture('houses', $layer);

        $feature = MapFeature::create([
            'layer_id' => $layer->id,
            'name' => $code,
            'category' => 'rumah',
            'geometry' => ['type' => 'Point', 'coordinates' => [110.9858, -7.3464]],
            'latitude' => -7.3464,
            'longitude' => 110.9858,
            'verification_status' => 'pending',
            'publication_status' => 'draft',
            'created_by' => $creator->id,
        ]);

        return House::create([
            'map_feature_id' => $feature->id,
            'house_code' => $code,
            'rt_code' => $rt,
            'address' => 'Alamat internal '.$code,
            'latitude' => -7.3464,
            'longitude' => 110.9858,
            'verification_status' => 'pending',
            'publication_status' => 'draft',
            'public_visible' => false,
            'created_by' => $creator->id,
        ]);
    }

    public function test_admin_rt_house_list_is_scoped_to_assigned_rt_and_owned_legacy_rows(): void
    {
        $rt01 = $this->user('admin_rt', '01');
        $rt02 = $this->user('admin_rt', '02');
        $own = $this->house($rt01, '01', 'BGN-RT01-OWN');
        $other = $this->house($rt02, '02', 'BGN-RT02-OTHER');
        $legacyOwn = $this->house($rt01, '01', 'BGN-RT01-LEGACY');
        $legacyOwn->forceFill(['rt_code' => null])->save();
        $legacyOther = $this->house($rt02, '02', 'BGN-LEGACY-OTHER');
        $legacyOther->forceFill(['rt_code' => null])->save();

        $response = $this->actingAs($rt01)->get(route('admin.houses.index'));

        $response->assertOk()
            ->assertSee($own->house_code)
            ->assertSee($legacyOwn->house_code)
            ->assertDontSee($other->house_code)
            ->assertDontSee($legacyOther->house_code);

        $mapResponse = $this->get(route('admin.map'));
        $mapResponse->assertOk()
            ->assertSee($own->house_code)
            ->assertSee($legacyOwn->house_code)
            ->assertDontSee($other->house_code)
            ->assertDontSee($legacyOther->house_code);
    }

    public function test_admin_rt_house_creation_automatically_uses_assigned_rt_and_saves_household_relations(): void
    {
        $rt01 = $this->user('admin_rt', '01');
        $layer = MapLayer::create([
            'name' => 'Rumah Warga',
            'slug' => 'rumah-warga-desa-pilangsari',
            'layer_type' => 'point',
            'color' => '#166534',
            'is_public' => false,
            'status' => 'draft',
        ]);
        $this->enablePublicationFixture('houses', $layer);

        $this->actingAs($rt01)->post(route('admin.houses.store'), [
            'house_label' => 'Rumah uji RT 01',
            'rt_code' => '99',
            'address' => 'Alamat survei internal RT 01',
            'latitude' => -7.3464,
            'longitude' => 110.9858,
            'public_visible' => '1',
            'households' => [
                [
                    'head_of_family_name' => 'Kepala Keluarga Uji',
                    'members' => [
                        ['full_name' => 'Anggota Keluarga Uji'],
                    ],
                ],
            ],
        ])->assertRedirect(route('admin.houses.index'));

        $house = House::where('house_label', 'Rumah uji RT 01')->firstOrFail();
        $this->assertSame('01', $house->rt_code);
        $this->assertSame($rt01->id, $house->created_by);
        $this->assertSame('pending', $house->verification_status);
        $this->assertSame('draft', $house->publication_status);
        $this->assertTrue($house->public_visible);
        $this->assertDatabaseHas('family_cards', [
            'house_id' => $house->id,
            'head_of_family_name' => 'Kepala Keluarga Uji',
            'head_name' => 'Kepala Keluarga Uji',
        ]);
        $this->assertDatabaseHas('family_members', [
            'full_name' => 'Anggota Keluarga Uji',
        ]);
        $this->assertSame('pending', $house->mapFeature->verification_status);
        $this->assertSame('draft', $house->mapFeature->publication_status);
    }

    public function test_admin_rt_without_assignment_sees_only_its_own_unassigned_legacy_records(): void
    {
        $unassigned = $this->user('admin_rt', null);
        $otherAdmin = $this->user('admin_rt', '02');
        $ownLegacy = $this->house($unassigned, '01', 'BGN-UNASSIGNED-OWN');
        $ownLegacy->forceFill(['rt_code' => null])->save();
        $otherLegacy = $this->house($otherAdmin, '02', 'BGN-UNASSIGNED-OTHER');
        $otherLegacy->forceFill(['rt_code' => null])->save();
        $assigned = $this->house($otherAdmin, '02', 'BGN-ASSIGNED-OTHER');

        $this->actingAs($unassigned)->get(route('admin.houses.index'))
            ->assertOk()
            ->assertSee($ownLegacy->house_code)
            ->assertDontSee($otherLegacy->house_code)
            ->assertDontSee($assigned->house_code);

        $this->get(route('admin.map'))
            ->assertOk()
            ->assertSee($ownLegacy->house_code)
            ->assertDontSee($otherLegacy->house_code)
            ->assertDontSee($assigned->house_code);
    }

    public function test_admin_rt_cannot_open_or_change_another_rt_house_by_direct_url(): void
    {
        $rt01 = $this->user('admin_rt', '01');
        $rt02 = $this->user('admin_rt', '02');
        $otherHouse = $this->house($rt02, '02', 'BGN-RT02-LOCKED');

        $this->actingAs($rt01)
            ->get(route('admin.houses.show', $otherHouse))->assertForbidden();
        $this->get(route('admin.houses.edit', $otherHouse))->assertForbidden();
        $this->put(route('admin.houses.update', $otherHouse), [
            'address' => 'Percobaan ubah lintas RT',
            'latitude' => -7.3464,
            'longitude' => 110.9858,
        ])->assertForbidden();
        $this->post(route('admin.houses.archive', $otherHouse))->assertForbidden();

        $this->assertSame('Alamat internal '.$otherHouse->house_code, $otherHouse->fresh()->address);
        $this->assertNull($otherHouse->fresh()->archived_at);
    }

    public function test_admin_rt_update_keeps_assigned_rt_and_resets_verification_and_publication(): void
    {
        $rt01 = $this->user('admin_rt', '01');
        $house = $this->house($rt01, '01', 'BGN-RT01-UPDATE');
        $house->update([
            'verification_status' => 'verified',
            'publication_status' => 'published',
            'public_visible' => true,
        ]);

        $this->actingAs($rt01)->put(route('admin.houses.update', $house), [
            'house_label' => 'Rumah diperbarui',
            'rt_code' => '99',
            'address' => 'Alamat hasil pembaruan',
            'latitude' => -7.3464,
            'longitude' => 110.9858,
            'public_visible' => '1',
        ])->assertRedirect(route('admin.houses.show', $house));

        $house->refresh();
        $this->assertSame('01', $house->rt_code);
        $this->assertSame('pending', $house->verification_status);
        $this->assertSame('draft', $house->publication_status);
        $this->assertTrue($house->public_visible);
        $this->assertSame('Alamat hasil pembaruan', $house->address);
    }

    public function test_admin_rt_can_archive_own_house_without_deleting_family_records(): void
    {
        $rt01 = $this->user('admin_rt', '01');
        $house = $this->house($rt01, '01', 'BGN-RT01-ARCHIVE');
        $card = FamilyCard::create([
            'house_id' => $house->id,
            'head_name' => 'Nama privat untuk uji',
            'head_of_family_name' => 'Nama privat untuk uji',
            'created_by' => $rt01->id,
        ]);
        $member = FamilyMember::create([
            'family_card_id' => $card->id,
            'full_name' => 'Anggota privat untuk uji',
            'created_by' => $rt01->id,
        ]);

        $this->actingAs($rt01)
            ->post(route('admin.houses.archive', $house))
            ->assertRedirect(route('admin.houses.index'));

        $this->assertNotNull($house->fresh()->archived_at);
        $this->assertDatabaseHas('family_cards', ['id' => $card->id, 'house_id' => $house->id]);
        $this->assertDatabaseHas('family_members', ['id' => $member->id, 'family_card_id' => $card->id]);
    }

    public function test_admin_rt_is_denied_super_admin_workflows_but_super_admin_can_review_all_rts(): void
    {
        $rt01 = $this->user('admin_rt', '01');
        $superAdmin = $this->user('super_admin');
        $rt02 = $this->user('admin_rt', '02');
        $house = $this->house($rt02, '02', 'BGN-RT02-REVIEW');

        $this->actingAs($rt01)->get(route('admin.verification.index'))->assertForbidden();
        $this->get(route('admin.facilities.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('admin.verification.index'))
            ->assertOk()
            ->assertSee($house->house_code);
    }

    public function test_super_admin_verification_and_publication_are_separate_and_sync_map_feature(): void
    {
        $superAdmin = $this->user('super_admin');
        $rt01 = $this->user('admin_rt', '01');
        $house = $this->house($rt01, '01', 'BGN-REVIEW-PUBLISH-001');
        $house->update(['public_visible' => true]);
        $house->mapFeature->layer->update(['is_public' => true, 'status' => 'published']);

        $this->actingAs($superAdmin)
            ->post(route('admin.houses.verify', $house))
            ->assertRedirect();

        $house->refresh();
        $house->mapFeature->refresh();
        $this->assertSame('verified', $house->verification_status);
        $this->assertSame('draft', $house->publication_status);
        $this->assertSame('verified', $house->mapFeature->verification_status);
        $this->assertSame('draft', $house->mapFeature->publication_status);

        $this->post(route('admin.houses.publish', $house), [
            'publication_declaration' => '1',
        ])->assertRedirect();
        $house->refresh();
        $house->mapFeature->refresh();
        $this->assertSame('published', $house->publication_status);
        $this->assertSame('published', $house->mapFeature->publication_status);

        $publicMap = $this->getJson('/api/map/public')->assertOk();
        $publicMap->assertJsonFragment(['name' => 'Lokasi rumah warga terverifikasi']);
        $versioned = $this->getJson('/api/v1/map/layers/'.$house->mapFeature->layer->slug.'/features');
        $versioned->assertOk()->assertJsonFragment(['name' => 'Lokasi rumah warga terverifikasi']);

        $this->post(route('admin.houses.unpublish', $house))->assertRedirect();
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/layers/'.$house->mapFeature->layer->slug.'/features')
            ->assertOk()->assertJsonCount(0, 'features');
    }

    public function test_super_admin_can_create_admin_rt_account_with_rt_assignment(): void
    {
        $superAdmin = $this->user('super_admin');

        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'name' => 'Admin RT 03',
            'email' => 'admin.rt03@example.test',
            'role' => 'admin_rt',
            'rt_code' => '03',
            'password' => 'Secure-Password-2026!',
            'password_confirmation' => 'Secure-Password-2026!',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'admin.rt03@example.test',
            'role' => 'admin_rt',
            'rt_code' => '03',
            'is_active' => 1,
        ]);
    }
}
