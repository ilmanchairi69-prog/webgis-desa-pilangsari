<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('webgis:status', function (): void {
    $this->info('WebGIS Desa Pilangsari — lingkungan aplikasi aktif.');
    $this->line('PHP: '.PHP_VERSION);
    $this->line('Laravel: '.app()->version());
    $this->line('Batas peta: public/data/Pilangsari_Referensi.geojson');
    $this->line('Data rumah dan fasilitas tidak di-seed; masukkan data hasil survei yang sah.');
})->purpose('Tampilkan status instalasi WebGIS.');
