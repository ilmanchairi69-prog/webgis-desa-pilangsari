<?php

namespace Database\Seeders;

use App\Services\AuditWriter;
use App\Services\WebGisSeedTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WebGisDemoSeeder extends Seeder
{
    public function run(): void
    {
        WebGisSeedTransaction::run(function (): void {
            $tables = ['map_layers', 'feature_categories', 'map_data_sources', 'houses', 'map_features', 'family_cards', 'family_members', 'facilities'];
            $before = [];
            foreach ($tables as $table) {
                $before[$table] = DB::table($table)->count();
            }
            $this->call([DatabaseSeeder::class, DummyPilangsariMapSeeder::class, DummyVillageFacilitySeeder::class]);
            $after = [];
            foreach ($tables as $table) {
                $after[$table] = DB::table($table)->count();
            }
            if ($before !== $after) {
                AuditWriter::write('demo.seeded', null, $before, $after + ['demonstration' => true]);
            }
        }, demonstration: true);
    }
}
