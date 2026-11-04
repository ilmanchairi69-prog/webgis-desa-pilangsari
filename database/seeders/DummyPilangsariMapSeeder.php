<?php

namespace Database\Seeders;

use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Services\DemonstrationCoordinates;
use App\Services\VillageBoundaryValidator;
use App\Services\WebGisSeedTransaction;
use Illuminate\Database\Seeder;
use RuntimeException;

class DummyPilangsariMapSeeder extends Seeder
{
    public function run(): void
    {
        WebGisSeedTransaction::run(function (): void {
            $layer = MapLayer::where('slug', 'rumah-warga-desa-pilangsari')->lockForUpdate()->first();
            if (! $layer || $layer->layer_type !== 'point') {
                throw new RuntimeException('Master layer rumah point wajib tersedia sebelum data demo.');
            }
            $points = app(DemonstrationCoordinates::class);
            for ($number = 1; $number <= 36; $number++) {
                $code = sprintf('BGN-DUMMY-%03d', $number);
                $existing = House::where('house_code', $code)->lockForUpdate()->first();
                if ($existing) {
                    $this->validateExisting($existing);

                    continue;
                }
                if (MapFeature::where('name', $code)->exists()) {
                    throw new RuntimeException('Kode demo berbenturan dengan fitur yang sudah ada; tidak ada data ditimpa.');
                }
                [$longitude, $latitude] = $points->point($number);
                $feature = MapFeature::create([
                    'layer_id' => $layer->id, 'name' => $code, 'category' => 'rumah',
                    'description' => 'DATA DEMONSTRASI SINTETIS - BUKAN DATA PENDUDUK.',
                    'geometry' => ['type' => 'Point', 'coordinates' => [$longitude, $latitude]],
                    'properties' => ['dummy' => true, 'seed_tag' => 'webgis-demo-v1', 'house_code' => $code],
                    'latitude' => $latitude, 'longitude' => $longitude,
                    'source_name' => 'Data demonstrasi sintetis WebGIS',
                    'verification_status' => 'pending', 'publication_status' => 'draft', 'created_by' => null,
                ]);
                $house = House::create([
                    'map_feature_id' => $feature->id, 'house_code' => $code,
                    'house_label' => 'DUMMY DEMONSTRASI '.sprintf('%03d', $number),
                    'address' => 'DATA SINTETIS UNTUK DEMO - BUKAN ALAMAT PENDUDUK.',
                    'rt_code' => null, 'latitude' => $latitude, 'longitude' => $longitude,
                    'verification_status' => 'pending', 'publication_status' => 'draft',
                    'public_visible' => false, 'created_by' => null, 'updated_by' => null,
                ]);
                for ($cardIndex = 1; $cardIndex <= 1 + ($number % 2 === 0 ? 1 : 0); $cardIndex++) {
                    $head = sprintf('DUMMY KEPALA %03d-%02d', $number, $cardIndex);
                    $card = FamilyCard::create([
                        'house_id' => $house->id, 'head_of_family_name' => $head, 'head_name' => $head,
                        'notes' => 'DEMONSTRASI SINTETIS; bukan KK penduduk. Nomor KK/NIK tidak dibuat.',
                    ]);
                    for ($memberIndex = 1; $memberIndex <= 2 + (($number + $cardIndex) % 3); $memberIndex++) {
                        FamilyMember::create([
                            'house_id' => $house->id, 'family_card_id' => $card->id,
                            'full_name' => sprintf('DUMMY ANGGOTA %03d-%02d-%02d', $number, $cardIndex, $memberIndex),
                            'relationship' => $memberIndex === 1 ? 'Kepala Keluarga' : 'Anggota',
                            'gender' => null, 'date_of_birth' => null,
                        ]);
                    }
                }
            }
        }, demonstration: true);
    }

    private function validateExisting(House $house): void
    {
        $feature = $house->mapFeature;
        if (! $feature || ($feature->properties['dummy'] ?? false) !== true
            || $feature->verification_status !== 'pending' || $feature->publication_status !== 'draft'
            || $house->verification_status !== 'pending' || $house->publication_status !== 'draft'
            || $house->public_visible || $house->archived_at !== null
            || ($feature->geometry['type'] ?? null) !== 'Point'
            || ! app(VillageBoundaryValidator::class)->geometryWithinBoundary($feature->geometry)
            || abs((float) $house->latitude - (float) $feature->latitude) > 0.0000001
            || abs((float) $house->longitude - (float) $feature->longitude) > 0.0000001) {
            throw new RuntimeException('Record kode dummy sudah ada tetapi tidak cocok dengan demo draft; ditinjau manual, tidak ditimpa.');
        }
        $cards = $house->familyCards()->with('members')->get();
        if ($cards->count() < 1 || $cards->count() > 2 || $cards->contains(fn (FamilyCard $card) => $card->members->count() < 2 || $card->members->count() > 4)) {
            throw new RuntimeException('Relasi keluarga dummy existing tidak lengkap; tidak ditimpa otomatis.');
        }
    }
}
