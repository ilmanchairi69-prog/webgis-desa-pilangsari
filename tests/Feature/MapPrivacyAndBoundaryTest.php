<?php

namespace Tests\Feature;

use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Services\VillageBoundaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapPrivacyAndBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_map_never_returns_private_family_names_or_card_number(): void
    {
        $layer = MapLayer::create([
            'name' => 'Rumah Publik Uji',
            'slug' => 'rumah-publik-privacy-test',
            'layer_type' => 'point',
            'color' => '#166534',
            'is_public' => true,
            'status' => 'published',
        ]);
        $this->enablePublicationFixture('houses', $layer);

        $feature = MapFeature::create([
            'layer_id' => $layer->id,
            'name' => 'TEST-PRIVACY-001',
            'category' => 'rumah',
            'geometry' => ['type' => 'Point', 'coordinates' => [110.9858, -7.3464]],
            'latitude' => -7.3464,
            'longitude' => 110.9858,
            'verification_status' => 'verified',
            'publication_status' => 'published',
        ]);

        $house = House::create([
            'map_feature_id' => $feature->id,
            'house_code' => 'TEST-PRIVACY-001',
            'rt_code' => '01',
            'address' => 'Alamat internal uji',
            'latitude' => -7.3464,
            'longitude' => 110.9858,
            'verification_status' => 'verified',
            'publication_status' => 'published',
            'public_visible' => true,
        ]);

        $card = FamilyCard::create([
            'house_id' => $house->id,
            'card_number' => 'TEST-KK-RAHASIA-001',
            'head_name' => 'Nama Kepala Rahasia Uji',
        ]);
        FamilyMember::create([
            'family_card_id' => $card->id,
            'full_name' => 'Nama Anggota Rahasia Uji',
            'relationship' => 'Anak',
        ]);

        $response = $this->getJson('/api/map/public')->assertOk();
        $response->assertJsonFragment(['name' => 'Lokasi rumah warga terverifikasi']);
        $response->assertDontSee('Nama Kepala Rahasia Uji');
        $response->assertDontSee('Nama Anggota Rahasia Uji');
        $response->assertDontSee('TEST-KK-RAHASIA-001');
        $response->assertDontSee('Alamat internal uji');
    }

    public function test_reference_boundary_accepts_a_point_inside_and_rejects_point_outside(): void
    {
        $service = app(VillageBoundaryService::class);
        $this->assertTrue($service->contains(110.9858, -7.3464));
        $this->assertFalse($service->contains(0.0, 0.0));
    }
}
