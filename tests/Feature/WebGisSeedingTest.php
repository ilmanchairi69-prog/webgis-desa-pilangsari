<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\User;
use App\Services\VillageBoundaryValidator;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\WebGisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class WebGisSeedingTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(): array
    {
        $result = [];
        foreach (['users', 'map_layers', 'feature_categories', 'map_data_sources', 'map_features', 'houses', 'family_cards', 'family_members', 'facilities', 'audit_logs', 'cache', 'sessions', 'jobs', 'failed_jobs'] as $table) {
            $result[$table] = DB::table($table)->orderByRaw('1')->get()->toJson();
        }

        return $result;
    }

    public function test_default_seeder_only_creates_master_and_is_idempotent_without_creating_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('map_layers', 2);
        $this->assertDatabaseCount('feature_categories', 10);
        $this->assertDatabaseCount('map_data_sources', 4);
        $this->assertDatabaseCount('houses', 0);
        $this->assertDatabaseCount('facilities', 0);
        $before = $this->snapshot();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_explicit_demo_seeds_complete_private_graph_and_preserves_accounts_on_repeat(): void
    {
        User::factory()->count(3)->create(['role' => 'admin_rt', 'rt_code' => '01']);
        $accounts = DB::table('users')->orderBy('id')->get()->toJson();
        $oldLog = AuditLog::create(['action' => 'existing.audit', 'entity_type' => 'system', 'created_at' => now()]);
        $this->seed(WebGisDemoSeeder::class);
        $this->assertSame($accounts, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertNotNull($oldLog->fresh());
        $this->assertDatabaseCount('houses', 36);
        $this->assertDatabaseCount('map_features', 36);
        $this->assertDatabaseCount('family_cards', 54);
        $this->assertDatabaseCount('family_members', 162);
        $this->assertDatabaseCount('facilities', 3);
        $this->assertDatabaseCount('audit_logs', 2);
        foreach (House::with('mapFeature', 'familyCards.members')->get() as $house) {
            $this->assertMatchesRegularExpression('/^BGN-DUMMY-\d{3}$/', $house->house_code);
            $this->assertSame('pending', $house->verification_status);
            $this->assertSame('draft', $house->publication_status);
            $this->assertFalse($house->public_visible);
            $this->assertNull($house->rt_code);
            $this->assertSame('Point', $house->mapFeature->geometry['type']);
            $this->assertTrue($house->mapFeature->properties['dummy']);
            $this->assertTrue(app(VillageBoundaryValidator::class)->geometryWithinBoundary($house->mapFeature->geometry));
            $this->assertSame((float) $house->longitude, (float) $house->mapFeature->longitude);
            $this->assertSame((float) $house->latitude, (float) $house->mapFeature->latitude);
            $this->assertContains($house->familyCards->count(), [1, 2]);
            foreach ($house->familyCards as $card) {
                $this->assertSame($card->head_name, $card->head_of_family_name);
                $this->assertNull($card->card_number);
                $this->assertGreaterThanOrEqual(2, $card->members->count());
                $this->assertLessThanOrEqual(4, $card->members->count());
                $this->assertTrue($card->members->every(fn ($member) => $member->house_id === $house->id && str_starts_with($member->full_name, 'DUMMY ANGGOTA')));
            }
        }
        foreach (Facility::all() as $facility) {
            $this->assertSame('draft', $facility->status);
            $this->assertStringContainsString('DUMMY', $facility->name);
            $this->assertTrue(app(VillageBoundaryValidator::class)->contains($facility->longitude, $facility->latitude));
        }
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(0, 'features')->assertDontSee('DUMMY KEPALA');
        $this->getJson('/api/v1/map/layers')->assertOk()->assertJsonCount(0, 'data');
        $before = $this->snapshot();
        $this->seed(WebGisDemoSeeder::class);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_legacy_layer_alias_is_renamed_in_place_preserving_references_and_boundary(): void
    {
        $layer = MapLayer::create(['slug' => 'fasilitas-desa', 'name' => 'Fasilitas Desa', 'layer_type' => 'point', 'is_public' => true, 'status' => 'published']);
        $feature = MapFeature::create(['layer_id' => $layer->id, 'name' => 'EXISTING FEATURE', 'category' => 'fasilitas', 'verification_status' => 'pending', 'publication_status' => 'draft']);
        $boundary = MapLayer::create(['slug' => 'batas-wilayah-desa', 'name' => 'Batas Wilayah Desa', 'layer_type' => 'polygon', 'is_public' => true, 'status' => 'published']);
        $originalBoundary = $boundary->fresh()->getRawOriginal();
        $featureBefore = $feature->fresh()->getRawOriginal();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame('fasilitas-umum-desa-pilangsari', $layer->fresh()->slug);
        $this->assertSame($layer->id, $feature->fresh()->layer_id);
        $this->assertTrue($layer->fresh()->is_public);
        $this->assertSame('published', $layer->fresh()->status);
        $this->assertSame($originalBoundary, $boundary->fresh()->getRawOriginal());
        $this->assertSame($featureBefore, $feature->fresh()->getRawOriginal());
        $this->assertDatabaseCount('map_layers', 3);
    }

    public function test_ambiguous_layer_identity_aborts_without_deleting_or_merging(): void
    {
        MapLayer::create(['slug' => 'fasilitas-desa', 'name' => 'Legacy facility', 'layer_type' => 'point']);
        MapLayer::create(['slug' => 'fasilitas-umum-desa-pilangsari', 'name' => 'Another facility', 'layer_type' => 'point']);
        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('Ambiguous master must abort');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ambigu', $exception->getMessage());
        }
        $this->assertDatabaseCount('map_layers', 2);
        $this->assertDatabaseCount('feature_categories', 0);
    }

    public function test_demo_failure_rolls_back_master_house_and_children_together(): void
    {
        House::creating(function (House $house): void {
            if ($house->house_code === 'BGN-DUMMY-005') {
                throw new RuntimeException('Controlled synthetic seed failure');
            }
        });
        try {
            $this->seed(WebGisDemoSeeder::class);
            $this->fail('Expected transaction failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Controlled synthetic seed failure', $exception->getMessage());
        }
        foreach (['map_layers', 'map_features', 'feature_categories', 'map_data_sources', 'houses', 'family_cards', 'family_members', 'facilities', 'audit_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_demo_does_not_overwrite_an_existing_house_code_collision(): void
    {
        $house = House::create(['house_code' => 'BGN-DUMMY-001', 'address' => 'Existing data', 'verification_status' => 'verified', 'publication_status' => 'published']);
        $before = $house->fresh()->getRawOriginal();
        try {
            $this->seed(WebGisDemoSeeder::class);
            $this->fail('Existing data must not be overwritten');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('tidak ditimpa', $exception->getMessage());
        }
        $this->assertSame($before, $house->fresh()->getRawOriginal());
        $this->assertDatabaseCount('map_layers', 0);
        $this->assertDatabaseCount('houses', 1);
    }

    public function test_demo_is_refused_in_production_without_writing_master_data(): void
    {
        $this->app->instance('env', 'production');
        try {
            (new WebGisDemoSeeder)->run();
            $this->fail('Production demo must be refused');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local/testing', $exception->getMessage());
        }
        $this->assertDatabaseCount('map_layers', 0);
        $this->assertDatabaseCount('houses', 0);
    }

    public function test_master_categories_and_sources_are_suggestions_on_existing_input_screens(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        $this->get(route('admin.features.create'))->assertOk()->assertSee('feature-category-master')->assertSee('value="rumah"', false)->assertSee('map-source-master')->assertSee('Batas referensi GeoJSON Pilangsari');
        $this->get(route('admin.facilities.create'))->assertOk()->assertSee('Fasilitas Umum')->assertSee('Pendidikan');
    }

    public function test_demo_can_be_reviewed_and_published_explicitly_using_existing_workflow(): void
    {
        $this->seed(WebGisDemoSeeder::class);
        $house = House::where('house_code', 'BGN-DUMMY-001')->firstOrFail();
        $house->update(['public_visible' => true]);
        $this->enablePublicationFixture('houses', $house->mapFeature->layer);
        $house->mapFeature->layer->update(['is_public' => true, 'status' => 'published']);
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        $this->post(route('admin.houses.verify', $house))->assertRedirect();
        $this->post(route('admin.houses.publish', $house), ['publication_declaration' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->getJson('/api/map/public')->assertOk()->assertJsonCount(1, 'features')->assertDontSee('DUMMY KEPALA')->assertDontSee('DUMMY ANGGOTA');
        $this->getJson('/api/v1/map/layers/rumah-warga-desa-pilangsari/features')->assertOk()->assertJsonCount(1, 'features');
    }
}
