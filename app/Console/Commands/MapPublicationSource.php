<?php

namespace App\Console\Commands;

use App\Models\MapLayer;
use App\Models\PublicationCategory;
use App\Services\AuditWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MapPublicationSource extends Command
{
    protected $signature = 'webgis:map-publication-source {system_key} {layer_id} {--apply : Simpan pemetaan yang telah diperiksa}';

    protected $description = 'Periksa/petakan sumber kategori sistem berdasarkan ID, tanpa memindah data atau mengubah visibilitas.';

    public function handle(): int
    {
        $key = (string) $this->argument('system_key');
        $id = (string) $this->argument('layer_id');
        if (! array_key_exists($key, PublicationCategory::Labels) || ! ctype_digit($id)) {
            $this->error('Kategori atau ID tidak valid.');

            return self::FAILURE;
        }
        try {
            DB::transaction(function () use ($key, $id): void {
                $category = PublicationCategory::whereKey($key)->lockForUpdate()->firstOrFail();
                $layer = MapLayer::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($layer->layer_type !== 'point' || $layer->status === 'archived') {
                    throw new RuntimeException('Sumber harus berupa layer point yang tidak diarsipkan.');
                }
                if (PublicationCategory::where('source_layer_id', $id)->where('system_key', '!=', $key)->exists()) {
                    throw new RuntimeException('Layer ini sudah dipetakan pada kategori sistem lain.');
                }
                $houseParents = DB::table('houses')->join('map_features', 'houses.map_feature_id', '=', 'map_features.id')->distinct()->pluck('map_features.layer_id');
                if ($key === PublicationCategory::Houses && $houseParents->isNotEmpty() && ($houseParents->count() !== 1 || (int) $houseParents->first() !== (int) $id)) {
                    throw new RuntimeException('Relasi rumah memakai layer berbeda/lebih dari satu layer. Tinjau manual; tidak ada data dipindahkan.');
                }
                if ($key === PublicationCategory::Facilities && $houseParents->contains(fn ($parent) => (int) $parent === (int) $id)) {
                    throw new RuntimeException('Layer ini digunakan House; tidak boleh dipetakan sebagai fasilitas.');
                }
                if ($category->source_layer_id !== null && (int) $category->source_layer_id !== (int) $id) {
                    throw new RuntimeException('Pemetaan existing tidak diganti otomatis. Tinjau ketergantungan dan rencana perbaikan terpisah.');
                }
                $this->line('Kategori '.$key.' -> layer ID '.$id.'; relasi rumah ditemukan: '.$houseParents->count().'. Fasilitas mandiri tidak mempunyai FK layer.');
                if ($this->option('apply') && $category->source_layer_id === null) {
                    $category->update(['source_layer_id' => (int) $id]);
                    AuditWriter::write('category.source_mapped', null, [], ['system_key' => $key, 'source_layer_id' => (int) $id]);
                }
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
