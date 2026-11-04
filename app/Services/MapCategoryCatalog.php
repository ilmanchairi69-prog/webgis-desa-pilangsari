<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MapCategoryCatalog
{
    public static function defaults(): array
    {
        return [
            ['name' => 'Rumah Warga', 'slug' => 'rumah', 'icon' => 'home'],
            ['name' => 'Fasilitas Umum', 'slug' => 'fasilitas-umum', 'icon' => 'building'],
            ['name' => 'Pendidikan', 'slug' => 'pendidikan', 'icon' => 'school'],
            ['name' => 'Kesehatan', 'slug' => 'kesehatan', 'icon' => 'heart'],
            ['name' => 'Ibadah', 'slug' => 'ibadah', 'icon' => 'place-of-worship'],
            ['name' => 'Pemerintahan', 'slug' => 'pemerintahan', 'icon' => 'landmark'],
            ['name' => 'Olahraga', 'slug' => 'olahraga', 'icon' => 'futbol'],
            ['name' => 'Infrastruktur', 'slug' => 'infrastruktur', 'icon' => 'road'],
            ['name' => 'Sosial', 'slug' => 'sosial', 'icon' => 'users'],
            ['name' => 'Lainnya', 'slug' => 'lainnya', 'icon' => 'map-marker'],
        ];
    }

    public static function choices(bool $facilitiesOnly = false): Collection
    {
        $rows = DB::table('feature_categories')->where('is_active', true)->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $rows = collect(self::defaults())->map(fn (array $row) => (object) $row);
        }

        return $rows->reject(fn (object $row) => $facilitiesOnly && $row->slug === 'rumah')
            ->map(fn (object $row) => ['value' => $row->slug === 'rumah' ? 'rumah' : $row->name, 'name' => $row->name, 'icon' => $row->icon]);
    }
}
