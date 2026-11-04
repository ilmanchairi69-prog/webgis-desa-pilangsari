<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WebGisSeedTransaction
{
    public static function run(Closure $operation, bool $demonstration = false): mixed
    {
        if ($demonstration && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Data demonstrasi hanya boleh dibuat pada lingkungan local/testing.');
        }
        $connection = DB::connection();
        if ($connection->getDriverName() === 'sqlite' && app()->environment('testing') && $connection->getDatabaseName() === ':memory:') {
            return $connection->transaction($operation);
        }
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Seeder memerlukan MySQL target atau SQLite in-memory untuk pengujian.');
        }
        $actual = $connection->selectOne('SELECT DATABASE() AS name')->name;
        if ($actual !== 'webgis_pilangsari' && ! (app()->environment('testing') && str_starts_with($actual, 'webgis_sqa_seed_'))) {
            throw new RuntimeException('Database aktif tidak diizinkan untuk seeder WebGIS.');
        }
        $lockName = 'webgis:seed:'.sha1($actual);
        $lock = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName], false);
        if ((int) $lock->acquired !== 1) {
            throw new RuntimeException('Seeder lain sedang berjalan; tidak ada data yang diubah.');
        }
        try {
            return $connection->transaction($operation);
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName], false);
        }
    }
}
