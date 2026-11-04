<?php

namespace Tests\Feature;

use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\PublicationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiPrivacyConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function publishedHouseFeature(bool $publicVisible, string $code): array
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $layer = MapLayer::create([
            'name' => 'Rumah Publik Uji',
            'slug' => 'rumah-publik-uji',
            'layer_type' => 'point',
            'color' => '#166534',
            'is_public' => true,
            'status' => 'published',
        ]);
        $this->enablePublicationFixture('houses', $layer);
        $feature = MapFeature::create([
            'layer_id' => $layer->id,
            'name' => $code,
            'category' => 'rumah',
            'geometry' => ['type' => 'Point', 'coordinates' => [110.9858, -7.3464]],
            'latitude' => -7.3464,
            'longitude' => 110.9858,
            'verification_status' => 'verified',
            'publication_status' => 'published',
            'created_by' => $user->id,
        ]);
        $house = House::create([
            'map_feature_id' => $feature->id,
            'house_code' => $code,
            'rt_code' => '01',
            'address' => 'Alamat privat API '.$code,
            'latitude' => -7.3464,
            'longitude' => 110.9858,
            'verification_status' => 'verified',
            'publication_status' => 'published',
            'public_visible' => $publicVisible,
            'created_by' => $user->id,
        ]);

        return [$layer, $feature, $house];
    }

    public function test_versioned_public_api_does_not_expose_house_without_public_consent(): void
    {
        [$layer, $feature] = $this->publishedHouseFeature(false, 'BGN-PRIVATE-CONSENT-001');

        $response = $this->getJson('/api/v1/map/layers/'.$layer->slug.'/features')
            ->assertOk();
        $this->assertNoStorePrivateCache($response);
        $response->assertJsonCount(0, 'features');

        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
        $this->getJson('/api/v1/map/search?q=BGN-PRIVATE-CONSENT-001')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_versioned_public_api_hides_archived_house_even_if_feature_is_still_published(): void
    {
        [$layer, $feature, $house] = $this->publishedHouseFeature(true, 'BGN-ARCHIVED-001');
        $house->update(['archived_at' => now()]);

        $this->getJson('/api/v1/map/layers/'.$layer->slug.'/features')
            ->assertOk()
            ->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
        $this->getJson('/api/v1/map/search?q=BGN-ARCHIVED-001')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unversioned_public_map_also_honors_consent_and_disables_stale_public_caching(): void
    {
        $this->publishedHouseFeature(false, 'BGN-PRIVATE-UNVERSIONED-001');

        $response = $this->getJson('/api/map/public')->assertOk();
        $response->assertJsonCount(0, 'features');
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertDontSee('BGN-PRIVATE-UNVERSIONED-001');
    }

    public function test_unversioned_public_map_keeps_eligible_house_visible_when_legacy_category_is_unpublished(): void
    {
        [$layer] = $this->publishedHouseFeature(true, 'BGN-DRAFT-LAYER-001');
        $layer->update(['is_public' => false, 'status' => 'draft']);
        PublicationCategory::findOrFail('houses')->update(['is_public' => false]);

        $response = $this->getJson('/api/map/public')->assertOk();
        $response->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.object_key', 'house:'.House::sole()->id)
            ->assertJsonPath('features.0.properties.name', 'Lokasi rumah warga terverifikasi');
        $response->assertDontSee('Alamat privat API');
    }

    public function test_versioned_public_api_hides_house_when_house_is_not_verified_and_published(): void
    {
        [$layer, $feature, $house] = $this->publishedHouseFeature(true, 'BGN-HOUSE-STATUS-001');
        $house->update(['verification_status' => 'pending', 'publication_status' => 'draft']);

        $this->getJson('/api/v1/map/layers/'.$layer->slug.'/features')
            ->assertOk()
            ->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
        $this->getJson('/api/v1/map/search?q=BGN-HOUSE-STATUS-001')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_versioned_public_api_returns_only_generic_map_properties_for_consented_house(): void
    {
        [$layer, $feature, $house] = $this->publishedHouseFeature(true, 'BGN-PUBLIC-GENERIC-001');
        $card = FamilyCard::create([
            'house_id' => $house->id,
            'head_name' => 'Nama Kepala Rahasia API',
            'head_of_family_name' => 'Nama Kepala Rahasia API',
            'card_number' => 'KK-RAHASIA-API-001',
        ]);
        FamilyMember::create([
            'family_card_id' => $card->id,
            'full_name' => 'Nama Anggota Rahasia API',
        ]);

        $this->getJson('/api/v1/map/layers/'.$layer->slug.'/features')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Lokasi rumah warga terverifikasi'])
            ->assertDontSee('Alamat privat API BGN-PUBLIC-GENERIC-001')
            ->assertDontSee('Nama Kepala Rahasia API')
            ->assertDontSee('Nama Anggota Rahasia API')
            ->assertDontSee('KK-RAHASIA-API-001');
    }

    public function test_versioned_public_api_hides_house_category_feature_without_related_house(): void
    {
        $layer = MapLayer::create([
            'name' => 'Rumah Orphan Uji',
            'slug' => 'rumah-orphan-uji',
            'layer_type' => 'point',
            'color' => '#166534',
            'is_public' => true,
            'status' => 'published',
        ]);
        $this->enablePublicationFixture('houses', $layer);
        $feature = MapFeature::create([
            'layer_id' => $layer->id,
            'name' => 'BGN-ORPHAN-HOUSE-001',
            'category' => 'rumah',
            'geometry' => ['type' => 'Point', 'coordinates' => [110.9858, -7.3464]],
            'latitude' => -7.3464,
            'longitude' => 110.9858,
            'verification_status' => 'verified',
            'publication_status' => 'published',
        ]);

        $this->getJson('/api/v1/map/layers/'.$layer->slug.'/features')
            ->assertOk()
            ->assertJsonCount(0, 'features');
        $this->getJson('/api/v1/map/features/'.$feature->id)->assertNotFound();
        $this->getJson('/api/v1/map/search?q=BGN-ORPHAN-HOUSE-001')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function assertNoStorePrivateCache($response): void
    {
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }
}
