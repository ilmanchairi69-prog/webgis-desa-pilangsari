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
use RuntimeException;
use Tests\TestCase;

class HouseholdEditingHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    private function household(User $creator, string $code = 'BGN-EDIT-MEMBERS', string $rt = '01'): array
    {
        $layer = MapLayer::firstOrCreate(['slug' => 'household-editing'], ['name' => 'Rumah Uji', 'layer_type' => 'point', 'is_public' => true, 'status' => 'published']);
        $feature = MapFeature::create(['layer_id' => $layer->id, 'name' => $code, 'category' => 'rumah', 'geometry' => ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]], 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'verification_status' => 'verified', 'publication_status' => 'published']);
        $house = House::create(['map_feature_id' => $feature->id, 'house_code' => $code, 'rt_code' => $rt, 'address' => 'Alamat semula privat', 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'verification_status' => 'verified', 'publication_status' => 'published', 'public_visible' => true, 'created_by' => $creator->id]);
        $card = FamilyCard::create(['house_id' => $house->id, 'head_name' => 'Kepala semula privat']);
        $member = FamilyMember::create(['house_id' => $house->id, 'family_card_id' => $card->id, 'full_name' => 'Anggota semula privat', 'relationship' => 'Anak', 'date_of_birth' => '2000-01-01', 'gender' => 'Perempuan']);

        return [$house, $card, $member];
    }

    private function payload(): array
    {
        return ['address' => 'Alamat sesudah privat', 'rt_code' => '01', 'latitude' => -7.3574198, 'longitude' => 110.9874323, 'public_visible' => '1'];
    }

    public static function roles(): array
    {
        return [['super_admin'], ['admin_rt']];
    }

    #[DataProvider('roles')]
    public function test_authorized_actor_can_edit_existing_household_members_without_leaking_them_to_audit(string $role): void
    {
        $actor = User::factory()->create(['role' => $role, 'rt_code' => '01', 'is_active' => true]);
        [$house, $card, $member] = $this->household($actor);
        $payload = $this->payload() + ['family_cards' => [$card->id => ['head_name' => 'Kepala baru privat', 'members' => [$member->id => ['full_name' => 'Anggota baru privat', 'relationship' => 'Saudara', 'date_of_birth' => '2001-02-03', 'gender' => 'Laki-laki']]]]];
        $this->actingAs($actor)->put(route('admin.houses.update', $house), $payload)->assertRedirect(route('admin.houses.show', $house))->assertSessionHasNoErrors();
        $this->assertSame('Kepala baru privat', $card->fresh()->head_name);
        $this->assertSame('Kepala baru privat', $card->fresh()->head_of_family_name);
        $this->assertSame('Anggota baru privat', $member->fresh()->full_name);
        $this->assertSame('Saudara', $member->fresh()->relationship);
        $this->assertSame('2001-02-03', $member->fresh()->date_of_birth->toDateString());
        $this->assertSame('Laki-laki', $member->fresh()->gender);
        $this->assertSame($actor->id, $member->fresh()->updated_by);
        $this->assertSame('pending', $house->fresh()->verification_status);
        $this->assertSame('draft', $house->fresh()->publication_status);
        $this->assertSame('draft', $house->mapFeature->fresh()->publication_status);
        $this->assertSame('01', $house->fresh()->rt_code);
        $this->getJson('/api/map/public')->assertJsonCount(0, 'features');
        $audit = AuditLog::where('action', 'house.updated')->sole();
        $this->assertSame([$member->id], $audit->changes['updated_family_member_ids']);
        $this->assertSame(1, $audit->changes['updated_family_member_count']);
        foreach (['Kepala semula privat', 'Kepala baru privat', 'Anggota semula privat', 'Anggota baru privat', '2001-02-03', 'Alamat semula privat', 'Alamat sesudah privat'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $audit->toJson());
        }
    }

    public static function forgedTargets(): array
    {
        return [['foreign card'], ['foreign member'], ['other card member'], ['missing card'], ['missing member'], ['malformed card'], ['malformed member']];
    }

    #[DataProvider('forgedTargets')]
    public function test_forged_child_ids_are_forbidden_and_roll_back_every_change(string $target): void
    {
        $actor = User::factory()->create(['role' => 'admin_rt', 'rt_code' => '01', 'is_active' => true]);
        [$house, $card, $member] = $this->household($actor);
        [$foreignHouse, $foreignCard, $foreignMember] = $this->household($actor, 'BGN-FOREIGN', '02');
        $secondCard = FamilyCard::create(['house_id' => $house->id, 'head_name' => 'Kepala KK kedua']);
        $secondMember = FamilyMember::create(['family_card_id' => $secondCard->id, 'full_name' => 'Anggota KK kedua']);
        $cards = [$card->id => ['head_name' => 'Perubahan harus rollback', 'members' => [$member->id => ['full_name' => 'Perubahan anggota harus rollback']]]];
        if (str_contains($target, 'card') && $target !== 'other card member') {
            $id = match ($target) {
                'foreign card' => $foreignCard->id, 'missing card' => 999999, default => 'invalid-id'
            };
            $cards[$id] = ['head_name' => 'Manipulasi KK'];
        } else {
            $id = match ($target) {
                'foreign member' => $foreignMember->id, 'other card member' => $secondMember->id, 'missing member' => 999999, default => 'invalid-id'
            };
            $cards[$card->id]['members'][$id] = ['full_name' => 'Manipulasi anggota'];
        }
        $this->actingAs($actor)->put(route('admin.houses.update', $house), $this->payload() + ['family_cards' => $cards])->assertForbidden();
        $this->assertSame('Alamat semula privat', $house->fresh()->address);
        $this->assertSame('published', $house->fresh()->publication_status);
        $this->assertSame('published', $house->mapFeature->fresh()->publication_status);
        $this->assertSame('Kepala semula privat', $card->fresh()->head_name);
        $this->assertSame('Anggota semula privat', $member->fresh()->full_name);
        $this->assertSame('Anggota semula privat', $foreignMember->fresh()->full_name);
        $this->assertSame('Anggota KK kedua', $secondMember->fresh()->full_name);
        $this->assertSame('02', $foreignHouse->fresh()->rt_code);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_member_values_are_rejected_without_any_mutation(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        [$house, $card, $member] = $this->household($actor);
        $this->actingAs($actor)->putJson(route('admin.houses.update', $house), $this->payload() + ['family_cards' => [$card->id => ['members' => [$member->id => ['full_name' => '', 'relationship' => str_repeat('r', 51), 'date_of_birth' => now()->addDay()->toDateString(), 'gender' => 'invalid']]]]])
            ->assertUnprocessable()->assertJsonValidationErrors(["family_cards.{$card->id}.members.{$member->id}.full_name", "family_cards.{$card->id}.members.{$member->id}.relationship", "family_cards.{$card->id}.members.{$member->id}.date_of_birth", "family_cards.{$card->id}.members.{$member->id}.gender"]);
        $this->assertSame('Alamat semula privat', $house->fresh()->address);
        $this->assertSame('Anggota semula privat', $member->fresh()->full_name);
        $this->assertSame('published', $house->fresh()->publication_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_optional_member_fields_can_be_cleared_without_deleting_the_member(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        [$house, $card, $member] = $this->household($actor);
        $this->actingAs($actor)->put(route('admin.houses.update', $house), $this->payload() + ['family_cards' => [$card->id => ['members' => [$member->id => ['full_name' => 'Anggota diperbaiki', 'relationship' => '', 'date_of_birth' => '', 'gender' => '']]]]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($member->fresh()->relationship);
        $this->assertNull($member->fresh()->date_of_birth);
        $this->assertNull($member->fresh()->gender);
        $this->assertDatabaseCount('family_members', 1);
    }

    public function test_omitted_optional_member_fields_are_preserved(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        [$house, $card, $member] = $this->household($actor);
        $this->actingAs($actor)->put(route('admin.houses.update', $house), $this->payload() + ['family_cards' => [$card->id => ['members' => [$member->id => ['full_name' => 'Nama diperbaiki']]]]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Anak', $member->fresh()->relationship);
        $this->assertSame('2000-01-01', $member->fresh()->date_of_birth->toDateString());
        $this->assertSame('Perempuan', $member->fresh()->gender);
        $this->assertSame('Kepala semula privat', $card->fresh()->head_name);
    }

    public function test_audit_failure_rolls_back_house_card_and_member_updates(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        [$house, $card, $member] = $this->household($actor);
        AuditLog::creating(function (): void {
            throw new RuntimeException('Simulated isolated audit failure');
        });
        try {
            $this->withoutExceptionHandling()->actingAs($actor)->put(route('admin.houses.update', $house), $this->payload() + ['family_cards' => [$card->id => ['head_name' => 'Nama KK rollback', 'members' => [$member->id => ['full_name' => 'Nama anggota rollback']]]]]);
            $this->fail('Expected isolated audit failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated isolated audit failure', $exception->getMessage());
        }
        $this->assertSame('Alamat semula privat', $house->fresh()->address);
        $this->assertSame('published', $house->fresh()->publication_status);
        $this->assertSame('published', $house->mapFeature->fresh()->publication_status);
        $this->assertSame('Kepala semula privat', $card->fresh()->head_name);
        $this->assertSame('Anggota semula privat', $member->fresh()->full_name);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
