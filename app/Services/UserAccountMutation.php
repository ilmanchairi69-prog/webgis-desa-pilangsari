<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccountMutation
{
    public static function run(Closure $operation): mixed
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            return $connection->transaction($operation);
        }

        $lockName = 'webgis:accounts:'.sha1($connection->getDatabaseName());
        $acquired = $connection->selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lockName], false);
        if ((int) $acquired->acquired !== 1) {
            throw ValidationException::withMessages([
                'account' => 'Pengelolaan akun sedang diproses. Coba lagi setelah proses sebelumnya selesai.',
            ]);
        }

        try {
            return $connection->transaction($operation);
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName], false);
        }
    }
}
