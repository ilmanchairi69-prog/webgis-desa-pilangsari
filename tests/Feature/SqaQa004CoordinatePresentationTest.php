<?php

namespace Tests\Feature;

use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SqaQa004CoordinatePresentationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('coordinateCases')]
    public function test_coordinate_presenter_distinguishes_missing_incomplete_invalid_and_valid_values(mixed $latitude, mixed $longitude, string $expected): void
    {
        $house = new House;
        $house->setRawAttributes(['latitude' => $latitude, 'longitude' => $longitude], true);
        $this->assertSame($expected, $house->coordinatePresentation()['label']);
    }

    public static function coordinateCases(): array
    {
        return [
            'missing' => [null, null, 'Koordinat belum tersedia'],
            'latitude missing' => [null, '110.123456', 'Koordinat tidak lengkap'],
            'longitude missing' => ['-7.123456', null, 'Koordinat tidak lengkap'],
            'valid' => ['-7.123456', '110.123456', '-7.123456, 110.123456'],
            'zero is valid' => [0, 0, '0.000000, 0.000000'],
            'latitude out of range' => [91, '110.123456', 'Koordinat tidak valid'],
            'longitude out of range' => ['-7.123456', 181, 'Koordinat tidak valid'],
            'nonnumeric' => ['teks', '110.123456', 'Koordinat tidak valid'],
            'minimum boundary' => [-90, -180, '-90.000000, -180.000000'],
            'maximum boundary' => [90, 180, '90.000000, 180.000000'],
        ];
    }

    public function test_verification_page_renders_each_coordinate_state_without_mutating_data(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $rows = [
            ['QA-COORD-MISSING', null, null],
            ['QA-COORD-INCOMPLETE-A', null, 110.123456],
            ['QA-COORD-INCOMPLETE-B', -7.123456, null],
            ['QA-COORD-VALID', -7.123456, 110.123456],
            ['QA-COORD-ZERO', 0, 0],
            ['QA-COORD-BAD-LAT', 91, 110.123456],
            ['QA-COORD-BAD-LNG', -7.123456, 181],
            ['QA-COORD-TEXT', 'teks', 110.123456],
            ['QA-COORD-MIN', -90, -180],
            ['QA-COORD-MAX', 90, 180],
        ];
        foreach ($rows as [$code, $latitude, $longitude]) {
            DB::table('houses')->insert([
                'house_code' => $code, 'address' => 'Alamat '.$code, 'latitude' => $latitude, 'longitude' => $longitude,
                'verification_status' => 'pending', 'publication_status' => 'draft', 'public_visible' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $before = DB::table('houses')->orderBy('id')->get()->toJson();

        $response = $this->actingAs($admin)->get(route('admin.verification.index'))->assertOk();
        $response->assertSee('Koordinat belum tersedia')->assertSee('Koordinat tidak lengkap')
            ->assertSee('Koordinat tidak valid')->assertSee('-7.123456, 110.123456')
            ->assertSee('0.000000, 0.000000')->assertSee('-90.000000, -180.000000')
            ->assertSee('90.000000, 180.000000');
        $this->assertSame($before, DB::table('houses')->orderBy('id')->get()->toJson());
    }

    public function test_house_detail_only_links_to_maps_for_valid_coordinates(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $missing = House::create(['house_code' => 'QA-DETAIL-MISSING', 'address' => 'Alamat', 'latitude' => null, 'longitude' => null]);
        $zero = House::create(['house_code' => 'QA-DETAIL-ZERO', 'address' => 'Alamat', 'latitude' => 0, 'longitude' => 0]);

        $this->actingAs($admin)->get(route('admin.houses.show', $missing))->assertOk()
            ->assertSee('Koordinat belum tersedia')->assertDontSee('Buka lokasi di Google Maps');
        $this->get(route('admin.houses.show', $zero))->assertOk()
            ->assertSee('0.0000000, 0.0000000')->assertSee('Buka lokasi di Google Maps');
    }
}
