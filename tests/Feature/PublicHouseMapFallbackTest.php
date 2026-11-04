<?php

namespace Tests\Feature;

use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHouseMapFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_map_uses_linked_map_feature_coordinates_when_house_coordinates_are_missing(): void
    {
        $layer = MapLayer::create([
            'name' => 'Rumah Warga',
            'slug' => 'rumah-warga-test',
            'layer_type' => 'point',
            'color' => '#166534',
            'is_public' => true,
            'status' => 'published',
        ]);
        $this->enablePublicationFixture('houses', $layer);

        $feature = MapFeature::create([
            'layer_id' => $layer->id,
            'name' => 'BGN-TEST-001',
            'category' => 'rumah',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [110.9874323, -7.3574198],
            ],
            'latitude' => -7.3574198,
            'longitude' => 110.9874323,
            'verification_status' => 'verified',
            'publication_status' => 'published',
        ]);

        House::create([
            'map_feature_id' => $feature->id,
            'house_code' => 'BGN-TEST-001',
            'address' => 'Alamat privat untuk pengujian',
            'latitude' => null,
            'longitude' => null,
            'verification_status' => 'verified',
            'publication_status' => 'published',
            'public_visible' => true,
        ]);

        $response = $this->getJson('/api/map/public')->assertOk();

        $response->assertJsonFragment([
            'coordinates' => [110.9874323, -7.3574198],
        ]);
        $response->assertJsonFragment([
            'name' => 'Lokasi rumah warga terverifikasi',
        ]);
        $response->assertDontSee('Alamat privat untuk pengujian');
        $response->assertDontSee('BGN-TEST-001');
    }
}
