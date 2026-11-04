<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\House;
use App\Models\PublicationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationVisibilityFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        PublicationCategory::query()->update(['is_public' => false]);
    }

    public function test_house_publication_feedback_confirms_individual_visibility_when_legacy_category_is_disabled(): void
    {
        $house = House::create(['house_code' => 'TEST-FEEDBACK', 'verification_status' => 'verified',
            'publication_status' => 'draft', 'public_visible' => true, 'latitude' => -7.3574198, 'longitude' => 110.9874323]);
        $this->post(route('admin.houses.publish', $house), ['publication_declaration' => true])
            ->assertRedirect()->assertSessionMissing('publication_settings_required')
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'dapat tampil') && str_contains($message, 'data keluarga tetap privat'));
        $this->assertSame('published', $house->fresh()->publication_status);
        $this->assertFalse(PublicationCategory::findOrFail('houses')->is_public);
        $this->getJson('/api/map/public')->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.object_key', 'house:'.$house->id);
        $this->patchJson('/admin/publication/houses', ['is_public' => true])->assertOk();
        $this->getJson('/api/map/public')->assertJsonCount(1, 'features');
        $this->post(route('admin.houses.publish', $house), ['publication_declaration' => true])
            ->assertRedirect()->assertSessionMissing('publication_settings_required');
    }

    public function test_facility_feedback_confirms_individual_visibility_when_legacy_category_is_disabled(): void
    {
        $facility = Facility::create(['name' => 'TEST-FACILITY', 'category' => 'Pendidikan',
            'latitude' => -7.3574198, 'longitude' => 110.9874323, 'status' => 'draft']);
        $this->post(route('admin.facilities.publish', $facility))->assertRedirect()
            ->assertSessionMissing('publication_settings_required')
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'dapat tampil') && str_contains($message, 'peta publik'));
        $this->assertSame('published', $facility->fresh()->status);
        $this->assertFalse(PublicationCategory::findOrFail('facilities')->is_public);
        $this->getJson('/api/map/public')->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.object_key', 'facility:'.$facility->id);
        $this->patchJson('/admin/publication/facilities', ['is_public' => true])->assertOk();
        $this->getJson('/api/map/public')->assertJsonCount(1, 'features');
    }

    public function test_publication_screen_reports_ready_objects_without_a_global_visibility_control(): void
    {
        House::create(['house_code' => 'TEST-READY', 'verification_status' => 'verified', 'publication_status' => 'published',
            'public_visible' => true, 'latitude' => -7.3574198, 'longitude' => 110.9874323]);
        $response = $this->get(route('admin.publication.index'))->assertOk();
        $response->assertViewHas('readyCounts', ['houses' => 1, 'facilities' => 0])
            ->assertSee('data-eligible-count="1"', false)
            ->assertSee('Status kategori lama tidak lagi menyembunyikan objek yang memenuhi syarat.')
            ->assertDontSee('type="checkbox"', false);
        $this->assertFalse(PublicationCategory::findOrFail('houses')->is_public);
    }
}
