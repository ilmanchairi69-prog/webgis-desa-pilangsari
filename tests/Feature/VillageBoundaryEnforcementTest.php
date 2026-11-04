<?php

namespace Tests\Feature;

use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VillageBoundaryEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const OUTSIDE_LONGITUDE = 110.98893225097656;

    private const OUTSIDE_LATITUDE = -7.35691982269287;

    private function superAdmin(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => 'super_admin',
            'is_active' => true,
        ])->save();

        return $user;
    }

    private function pointLayer(string $slug = 'test-point-layer'): MapLayer
    {
        $layer = MapLayer::create([
            'name' => 'Layer Tes',
            'slug' => $slug,
            'layer_type' => 'point',
            'color' => '#16803c',
            'is_public' => false,
            'status' => 'published',
        ]);
        if ($slug === 'rumah-warga-desa-pilangsari') {
            $this->enablePublicationFixture('houses', $layer);
        }

        return $layer;
    }

    public function test_house_creation_outside_village_boundary_is_rejected(): void
    {
        $this->actingAs($this->superAdmin());

        $this->pointLayer('rumah-warga-desa-pilangsari');

        $response = $this->from(route('admin.houses.create'))
            ->post(route('admin.houses.store'), [
                'rt' => '001',
                'rw' => '001',
                'address' => 'Alamat internal untuk pengujian',
                'public_visible' => '1',
                'latitude' => self::OUTSIDE_LATITUDE,
                'longitude' => self::OUTSIDE_LONGITUDE,
                'households' => [
                    [
                        'head_of_family_name' => 'Nama Uji Integrasi',
                        'members' => [],
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.houses.create'));
        $response->assertSessionHasErrors('latitude');

        $this->assertDatabaseCount('houses', 0);
        $this->assertDatabaseCount('map_features', 0);
        $this->assertDatabaseCount('family_cards', 0);
    }

    public function test_map_feature_creation_outside_village_boundary_is_rejected(): void
    {
        $this->actingAs($this->superAdmin());

        $layer = $this->pointLayer();

        $response = $this->from(route('admin.features.create'))
            ->post(route('admin.features.store'), [
                'layer_id' => $layer->id,
                'name' => 'Fitur di Luar Batas',
                'latitude' => self::OUTSIDE_LATITUDE,
                'longitude' => self::OUTSIDE_LONGITUDE,
            ]);

        $response->assertRedirect(route('admin.features.create'));
        $response->assertSessionHasErrors('latitude');

        $this->assertDatabaseCount('map_features', 0);
    }

    public function test_map_feature_update_outside_village_boundary_preserves_existing_data(): void
    {
        $this->actingAs($this->superAdmin());

        $layer = $this->pointLayer();

        $feature = MapFeature::create([
            'layer_id' => $layer->id,
            'name' => 'Fitur Dalam Batas',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    110.98743225097655,
                    -7.35741982269287,
                ],
            ],
            'latitude' => -7.3574198,
            'longitude' => 110.9874323,
            'verification_status' => 'verified',
            'publication_status' => 'published',
        ]);

        $originalLatitude = $feature->latitude;
        $originalLongitude = $feature->longitude;

        $response = $this->from(route('admin.features.edit', $feature))
            ->put(route('admin.features.update', $feature), [
                'layer_id' => $layer->id,
                'name' => 'Perubahan di Luar Batas',
                'latitude' => self::OUTSIDE_LATITUDE,
                'longitude' => self::OUTSIDE_LONGITUDE,
            ]);

        $response->assertRedirect(route('admin.features.edit', $feature));
        $response->assertSessionHasErrors('latitude');

        $feature->refresh();

        $this->assertEquals('Fitur Dalam Batas', $feature->name);
        $this->assertEquals((float) $originalLatitude, (float) $feature->latitude);
        $this->assertEquals((float) $originalLongitude, (float) $feature->longitude);
        $this->assertSame('verified', $feature->verification_status);
        $this->assertSame('published', $feature->publication_status);
        $this->assertDatabaseCount('map_features', 1);
    }

    public function test_house_inside_village_boundary_can_be_created(): void
    {
        $this->actingAs($this->superAdmin());

        $this->pointLayer('rumah-warga-desa-pilangsari');

        $response = $this->post(route('admin.houses.store'), [
            'rt' => '001',
            'rw' => '001',
            'address' => 'Alamat internal untuk pengujian',
            'public_visible' => '1',
            'latitude' => -7.35741982269287,
            'longitude' => 110.98743225097655,
            'households' => [
                [
                    'head_of_family_name' => 'Nama Uji Integrasi',
                    'members' => [],
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('houses', 1);
        $this->assertDatabaseCount('map_features', 1);
        $this->assertDatabaseCount('family_cards', 1);

        $this->assertDatabaseHas('map_features', [
            'verification_status' => 'pending',
            'publication_status' => 'draft',
        ]);
    }

    public function test_line_crossing_village_boundary_is_rejected_by_feature_store(): void
    {
        $this->actingAs($this->superAdmin());

        $layer = MapLayer::create([
            'name' => 'Layer Garis Tes',
            'slug' => 'test-line-layer',
            'layer_type' => 'line',
            'color' => '#16803c',
            'is_public' => false,
            'status' => 'published',
        ]);

        $crossingLine = [
            'type' => 'LineString',
            'coordinates' => [
                [110.98743225097655, -7.35741982269287],
                [110.99043225097657, -7.35641982269287],
            ],
        ];

        $response = $this->from(route('admin.features.create'))
            ->post(route('admin.features.store'), [
                'layer_id' => $layer->id,
                'name' => 'Garis Melintasi Batas',
                'geometry' => json_encode($crossingLine, JSON_THROW_ON_ERROR),
            ]);

        $response->assertRedirect(route('admin.features.create'));
        $response->assertSessionHasErrors('geometry');

        $this->assertDatabaseCount('map_features', 0);
    }

    public function test_admin_rt_cannot_create_map_features(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => 'admin_rt',
            'is_active' => true,
            'assigned_rt' => '001',
        ])->save();

        $this->actingAs($user);

        $layer = $this->pointLayer();

        $response = $this->post(route('admin.features.store'), [
            'layer_id' => $layer->id,
            'name' => 'Percobaan Admin RT',
            'latitude' => -7.35741982269287,
            'longitude' => 110.98743225097655,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('map_features', 0);
    }
}
