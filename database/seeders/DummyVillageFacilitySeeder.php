<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\MapLayer;
use App\Services\DemonstrationCoordinates;
use App\Services\VillageBoundaryValidator;
use App\Services\WebGisSeedTransaction;
use Illuminate\Database\Seeder;
use RuntimeException;

class DummyVillageFacilitySeeder extends Seeder
{
    public function run(): void
    {
        WebGisSeedTransaction::run(function (): void {
            $layer = MapLayer::where('slug', 'fasilitas-umum-desa-pilangsari')->lockForUpdate()->first();
            if (! $layer || $layer->layer_type !== 'point') {
                throw new RuntimeException('Master layer fasilitas point belum tersedia.');
            }
            $points = app(DemonstrationCoordinates::class);
            foreach (['Pendidikan', 'Kesehatan', 'Pemerintahan'] as $index => $category) {
                $name = sprintf('DUMMY FASILITAS %03d - %s', $index + 1, $category);
                $existing = Facility::where('name', $name)->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== 1 || ! str_contains((string) $existing[0]->description, '[WEBGIS-DEMO-V1]')
                        || $existing[0]->status !== 'draft' || $existing[0]->archived_at !== null
                        || ! app(VillageBoundaryValidator::class)->contains((float) $existing[0]->longitude, (float) $existing[0]->latitude)) {
                        throw new RuntimeException('Nama demo fasilitas berbenturan dengan record existing; tidak ditimpa.');
                    }

                    continue;
                }
                [$longitude, $latitude] = $points->point(1001 + $index);
                Facility::create([
                    'name' => $name, 'category' => $category,
                    'description' => '[WEBGIS-DEMO-V1] DEMONSTRASI SINTETIS, bukan fasilitas desa sebenarnya.',
                    'address' => 'Titik sintetis untuk pengujian lokal, bukan alamat fasilitas sebenarnya.',
                    'latitude' => $latitude, 'longitude' => $longitude, 'status' => 'draft',
                ]);
            }
        }, demonstration: true);
    }
}
