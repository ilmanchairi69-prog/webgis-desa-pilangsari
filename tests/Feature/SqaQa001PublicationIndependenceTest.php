<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\PublicationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqaQa001PublicationIndependenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PublicationCategory::query()->update(['is_public' => false, 'source_layer_id' => null]);
    }

    public function test_eligible_objects_remain_on_every_public_collection_when_global_categories_are_disabled(): void
    {
        $house = $this->house();
        $facility = $this->facility();
        $card = FamilyCard::create(['house_id' => $house->id, 'head_name' => 'PRIVATE-QA001-HEAD', 'card_number' => 'PRIVATE-QA001-KK']);
        FamilyMember::create(['family_card_id' => $card->id, 'full_name' => 'PRIVATE-QA001-MEMBER']);

        foreach (['/api/map/public', '/api/v1/map/objects'] as $endpoint) {
            $response = $this->getJson($endpoint)->assertOk()->assertJsonCount(2, 'features')->assertDontSee('PRIVATE-QA001');
            $keys = collect($response->json('features'))->pluck('properties.object_key')->all();
            $this->assertContains('house:'.$house->id, $keys);
            $this->assertContains('facility:'.$facility->id, $keys);
        }

        $this->getJson('/api/v1/map/objects/house/'.$house->id)->assertOk()
            ->assertJsonPath('geometry.coordinates', [110.9874323, -7.3574198]);
        $this->getJson('/api/v1/map/objects/facility/'.$facility->id)->assertOk()
            ->assertJsonPath('geometry.coordinates', [110.9894323, -7.3554198]);
        $this->getJson('/api/v1/map/search?q=QA001')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.object_key', 'facility:'.$facility->id);
        $this->getJson('/api/map/public')->assertJsonPath('meta.categories.0.controls_visibility', false)
            ->assertJsonPath('meta.categories.1.controls_visibility', false);
        $this->assertDatabaseCount('map_layers', 0);
        $this->assertDatabaseCount('map_features', 0);
    }

    public function test_only_individual_eligibility_controls_house_visibility(): void
    {
        $house = $this->house();
        foreach ([
            ['verification_status' => 'pending'],
            ['verification_status' => 'rejected'],
            ['publication_status' => 'draft'],
            ['publication_status' => 'archived'],
            ['archived_at' => now()],
            ['public_visible' => false],
            ['latitude' => null],
            ['longitude' => null],
            ['latitude' => 91],
            ['longitude' => 181],
        ] as $changes) {
            $original = $house->getAttributes();
            $house->update($changes);
            $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
            DB::table('houses')->where('id', $house->id)->update($original);
            $house->refresh();
        }

        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(1, 'features');
    }

    public function test_only_individual_eligibility_controls_facility_visibility(): void
    {
        $facility = $this->facility();
        foreach ([
            ['status' => 'draft'],
            ['status' => 'archived'],
            ['archived_at' => now()],
            ['latitude' => 91],
            ['longitude' => 181],
            ['name' => ''],
            ['category' => ''],
        ] as $changes) {
            $original = $facility->getAttributes();
            $facility->update($changes);
            $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features');
            DB::table('facilities')->where('id', $facility->id)->update($original);
            $facility->refresh();
        }

        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(1, 'features');
    }

    private function house(array $changes = []): House
    {
        return House::create(array_merge([
            'house_code' => 'QA001-HOUSE', 'address' => 'PRIVATE-QA001-ADDRESS', 'rt_code' => '01',
            'latitude' => -7.3574198, 'longitude' => 110.9874323,
            'verification_status' => 'verified', 'publication_status' => 'published', 'public_visible' => true,
        ], $changes));
    }

    private function facility(array $changes = []): Facility
    {
        return Facility::create(array_merge([
            'name' => 'QA001 Public Clinic', 'category' => 'Kesehatan',
            'latitude' => -7.3554198, 'longitude' => 110.9894323, 'status' => 'published',
        ], $changes));
    }
}
