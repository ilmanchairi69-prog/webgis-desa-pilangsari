<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only diagnostic for the WebGIS map-layer relationships.
 * This command deliberately does not update, delete, or migrate any data.
 */
class AuditMapLayerDependencies extends Command
{
    protected $signature = 'webgis:audit-layer-dependencies';

    protected $description = 'Audit read-only dependencies between houses, map features, and map layers';

    public function handle(): int
    {
        $this->components->info('WebGIS Pilangsari — audit dependensi layer (READ ONLY)');

        foreach (['houses', 'map_features', 'map_layers'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->components->error("Tabel {$table} tidak ditemukan.");
                return self::FAILURE;
            }
        }

        $houseFeatureColumn = Schema::hasColumn('houses', 'map_feature_id');
        $featureLayerColumn = Schema::hasColumn('map_features', 'layer_id');

        if (! $houseFeatureColumn || ! $featureLayerColumn) {
            $this->components->error('Kolom relasi houses.map_feature_id atau map_features.layer_id tidak ditemukan.');
            return self::FAILURE;
        }

        try {
            $housesTotal = DB::table('houses')->count();
            $featuresTotal = DB::table('map_features')->count();
            $layersTotal = DB::table('map_layers')->count();

            $housesWithoutFeature = DB::table('houses')
                ->whereNull('map_feature_id')
                ->count();

            $housesWithMissingFeature = DB::table('houses as h')
                ->leftJoin('map_features as mf', 'mf.id', '=', 'h.map_feature_id')
                ->whereNotNull('h.map_feature_id')
                ->whereNull('mf.id')
                ->count();

            $featuresWithoutLayer = DB::table('map_features')
                ->whereNull('layer_id')
                ->count();

            $featuresWithMissingLayer = DB::table('map_features as mf')
                ->leftJoin('map_layers as ml', 'ml.id', '=', 'mf.layer_id')
                ->whereNotNull('mf.layer_id')
                ->whereNull('ml.id')
                ->count();

            $this->table(
                ['Pemeriksaan', 'Jumlah'],
                [
                    ['Total rumah', $housesTotal],
                    ['Total fitur peta', $featuresTotal],
                    ['Total layer', $layersTotal],
                    ['Rumah tanpa map_feature_id', $housesWithoutFeature],
                    ['Rumah dengan relasi fitur yatim', $housesWithMissingFeature],
                    ['Fitur tanpa layer_id', $featuresWithoutLayer],
                    ['Fitur dengan relasi layer yatim', $featuresWithMissingLayer],
                ]
            );

            $this->newLine();
            $this->components->warn('Audit ini hanya membaca database. Tidak ada data, view, route, atau migration yang diubah.');
            $this->line('Catatan: relasi yang lengkap belum membuktikan bahwa layer wajib untuk seluruh alur bisnis rumah.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('Audit gagal membaca database. Periksa konfigurasi koneksi dan log aplikasi.');

            return self::FAILURE;
        }
    }
}
