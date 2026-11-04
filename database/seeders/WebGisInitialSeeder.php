<?php

namespace Database\Seeders;

use App\Models\MapLayer;
use App\Services\MapCategoryCatalog;
use App\Services\WebGisSeedTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WebGisInitialSeeder extends Seeder
{
    public function run(): void
    {
        WebGisSeedTransaction::run(function (): void {
            $this->layer('rumah-warga-desa-pilangsari', 'Rumah Warga Desa Pilangsari', ['titik-rumah', 'rumah-warga'], ['Titik Rumah'], '#2563eb');
            $this->layer('fasilitas-umum-desa-pilangsari', 'Fasilitas Umum Desa Pilangsari', ['fasilitas-desa', 'fasilitas-umum'], ['Fasilitas Desa'], '#16a34a');
            foreach (MapCategoryCatalog::defaults() as $category) {
                if (! DB::table('feature_categories')->where('slug', $category['slug'])->exists()) {
                    DB::table('feature_categories')->insert($category + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $sources = [
                ['name' => 'Survei lapangan (rencana pengisian)', 'source_type' => 'field_survey', 'description' => 'Penampung metadata survei yang kelak dilakukan; belum menyatakan survei telah terlaksana.'],
                ['name' => 'Batas referensi GeoJSON Pilangsari', 'source_type' => 'geojson_reference', 'description' => 'Berkas public/data/Pilangsari_Referensi.geojson yang tersedia di aplikasi; bukan penetapan batas resmi.'],
                ['name' => 'Inventaris fasilitas desa (rencana pengisian)', 'source_type' => 'facility_inventory', 'description' => 'Penampung sumber inventaris fasilitas; menunggu data dan verifikasi pengelola.'],
                ['name' => 'Data demonstrasi sintetis WebGIS', 'source_type' => 'synthetic_demo', 'description' => 'Data buatan untuk pengujian lokal, bukan data penduduk atau fasilitas sebenarnya.'],
            ];
            foreach ($sources as $source) {
                if (! DB::table('map_data_sources')->where('name', $source['name'])->where('source_type', $source['source_type'])->exists()) {
                    DB::table('map_data_sources')->insert($source + [
                        'license_notes' => $source['source_type'] === 'synthetic_demo' ? 'Data sintetis aplikasi; tidak menyatakan lisensi sumber eksternal.' : 'Sumber resmi, kewenangan dan lisensi belum diverifikasi.',
                        'verification_status' => 'pending', 'acquired_at' => null, 'created_by' => null, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    private function layer(string $slug, string $name, array $aliases, array $names, string $color): void
    {
        $candidates = MapLayer::whereIn('slug', [$slug, ...$aliases])->orWhereIn('name', [$name, ...$names])->lockForUpdate()->get();
        if ($candidates->count() > 1) {
            throw new RuntimeException('Identitas layer ambigu; tidak ada layer digabung atau dihapus.');
        }
        $layer = $candidates->first();
        if ($layer) {
            if ($layer->layer_type !== 'point') {
                throw new RuntimeException('Layer master yang ditemukan bukan point; relasi dipertahankan dan seeding dibatalkan.');
            }
            if ($layer->slug !== $slug || $layer->name !== $name) {
                $layer->update(['slug' => $slug, 'name' => $name]);
            }
        } else {
            MapLayer::create([
                'slug' => $slug, 'name' => $name, 'layer_type' => 'point', 'color' => $color,
                'is_public' => false, 'status' => 'draft',
                'description' => 'Master layer; publikasi dan verifikasi dilakukan terpisah oleh pengelola.',
                'source_name' => null, 'source_notes' => 'Tidak menyatakan data telah disurvei atau diverifikasi.',
            ]);
        }
    }
}
