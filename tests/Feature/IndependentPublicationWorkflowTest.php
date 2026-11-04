<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndependentPublicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function house(array $attributes = []): House
    {
        return House::create(array_merge([
            'house_code' => 'TEST-INDEPENDENT', 'map_feature_id' => null,
            'latitude' => -7.3574198, 'longitude' => 110.9874323,
            'verification_status' => 'pending', 'publication_status' => 'draft',
            'public_visible' => true,
        ], $attributes));
    }

    private function signInSuperAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_house_without_feature_or_layer_can_be_verified_published_and_unpublished(): void
    {
        $this->signInSuperAdmin();
        $house = $this->house();
        $this->post(route('admin.houses.verify', $house))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('verified', $house->fresh()->verification_status);
        $this->post(route('admin.houses.publish', $house), ['publication_declaration' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('published', $house->fresh()->publication_status);
        $this->assertNotNull($house->fresh()->published_at);
        $this->assertNull($house->fresh()->map_feature_id);
        $this->assertDatabaseCount('map_features', 0);
        $this->assertDatabaseCount('map_layers', 0);
        $this->post(route('admin.houses.unpublish', $house))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', $house->fresh()->publication_status);
        $this->assertSame('verified', $house->fresh()->verification_status);
        $this->assertNull($house->fresh()->published_at);
        $this->assertDatabaseCount('houses', 1);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_house_without_feature_can_be_rejected_without_exposing_family_data_in_audit(): void
    {
        $this->signInSuperAdmin();
        $house = $this->house();
        $card = FamilyCard::create(['house_id' => $house->id, 'head_name' => 'PRIVATE-HEAD']);
        FamilyMember::create(['family_card_id' => $card->id, 'full_name' => 'PRIVATE-MEMBER']);
        $this->post(route('admin.houses.reject', $house), ['rejection_note' => 'Koordinat perlu ditinjau'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('rejected', $house->fresh()->verification_status);
        $this->assertFalse($house->fresh()->public_visible);
        $audit = AuditLog::firstOrFail()->toJson();
        $this->assertStringNotContainsString('PRIVATE-HEAD', $audit);
        $this->assertStringNotContainsString('PRIVATE-MEMBER', $audit);
        $this->assertDatabaseCount('family_cards', 1);
        $this->assertDatabaseCount('family_members', 1);
    }

    public function test_archived_parent_and_feature_status_do_not_block_verified_house_publication(): void
    {
        $this->signInSuperAdmin();
        $layer = MapLayer::create(['name' => 'Inactive Storage', 'slug' => 'inactive-storage', 'layer_type' => 'point', 'status' => 'archived', 'is_public' => false]);
        $feature = MapFeature::create(['layer_id' => $layer->id, 'name' => 'Legacy', 'verification_status' => 'pending', 'publication_status' => 'archived']);
        $house = $this->house(['map_feature_id' => $feature->id, 'verification_status' => 'verified']);
        $this->post(route('admin.houses.publish', $house), ['publication_declaration' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('published', $house->fresh()->publication_status);
        $this->assertSame('published', $feature->fresh()->publication_status);
        $this->assertSame('archived', $layer->fresh()->status);
        $this->assertFalse($layer->fresh()->is_public);
    }

    public static function invalidCoordinates(): array
    {
        return [
            'missing latitude' => [null, 110.9874323],
            'missing longitude' => [-7.3574198, null],
            'outside village' => [-6.2, 106.8],
            'out of range' => [91, 181],
        ];
    }

    #[DataProvider('invalidCoordinates')]
    public function test_invalid_house_coordinates_cannot_be_published(?float $latitude, ?float $longitude): void
    {
        $this->signInSuperAdmin();
        $house = $this->house(['latitude' => $latitude, 'longitude' => $longitude, 'verification_status' => 'verified']);
        $this->post(route('admin.houses.publish', $house), ['publication_declaration' => '1'])
            ->assertRedirect()->assertSessionHasErrors('publication');
        $this->assertSame('draft', $house->fresh()->publication_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_consent_verification_and_declaration_remain_required_without_feature(): void
    {
        $this->signInSuperAdmin();
        $house = $this->house();
        $this->post(route('admin.houses.publish', $house), ['publication_declaration' => '1'])
            ->assertSessionHasErrors('publication');
        $house->update(['verification_status' => 'verified', 'public_visible' => false]);
        $this->post(route('admin.houses.publish', $house), ['publication_declaration' => '1'])
            ->assertSessionHasErrors('publication');
        $house->update(['public_visible' => true]);
        $this->post(route('admin.houses.publish', $house))->assertSessionHasErrors('publication_declaration');
        $this->assertSame('draft', $house->fresh()->publication_status);
    }

    public function test_archived_house_cannot_be_mutated_by_verification_actions(): void
    {
        $this->signInSuperAdmin();
        $house = $this->house(['publication_status' => 'archived']);
        foreach (['verify', 'reject', 'publish', 'unpublish'] as $action) {
            $this->post(route('admin.houses.'.$action, $house), ['publication_declaration' => '1', 'rejection_note' => 'Tidak boleh berubah'])
                ->assertNotFound();
        }
        $this->assertSame('archived', $house->fresh()->publication_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rt_admin_cannot_verify_or_publish_an_unlinked_house(): void
    {
        $admin = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        $house = $this->house(['rt_code' => '01']);
        $this->actingAs($admin);
        foreach (['verify', 'reject', 'publish', 'unpublish'] as $action) {
            $this->post(route('admin.houses.'.$action, $house), ['publication_declaration' => '1', 'rejection_note' => 'Tidak boleh berubah'])
                ->assertForbidden();
        }
        $this->assertSame('pending', $house->fresh()->verification_status);
    }
}
