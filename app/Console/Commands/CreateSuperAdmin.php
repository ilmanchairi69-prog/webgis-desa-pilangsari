<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditWriter;
use App\Services\UserAccountMutation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'webgis:make-super-admin';

    protected $description = 'Buat akun Super Admin pertama untuk WebGIS Desa Pilangsari.';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Jalankan perintah secara interaktif untuk memasukkan kredensial dengan aman.');

            return self::FAILURE;
        }

        if (User::where('role', 'super_admin')->exists()) {
            $this->error('Super Admin sudah tersedia. Perintah dibatalkan demi mencegah duplikasi akun awal.');

            return self::FAILURE;
        }
        $name = trim((string) $this->ask('Nama Super Admin'));
        $email = strtolower(trim((string) $this->ask('Email login')));
        $password = (string) $this->secret('Kata sandi (minimal 12 karakter)', false);
        $confirmation = (string) $this->secret('Ulangi kata sandi', false);
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:12', 'max:200', 'confirmed'],
        ]);

        if ($validator->fails() || (config('hashing.driver') === 'bcrypt' && strlen($password) > 72)) {
            $this->error('Data tidak valid. Nama maksimal 255 karakter, email valid maksimal 190 karakter, kata sandi minimal 12 karakter dan harus sama. Untuk bcrypt maksimal 72 byte.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('Email sudah digunakan.');

            return self::FAILURE;
        }
        $created = UserAccountMutation::run(function () use ($name, $email, $password): bool {
            if (User::where('role', 'super_admin')->exists() || User::where('email', $email)->exists()) {
                return false;
            }
            $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'super_admin', 'rt_code' => null, 'is_active' => true]);
            AuditWriter::write('user.bootstrap_created', $user, [], ['email' => $user->email, 'role' => $user->role]);

            return true;
        });
        if (! $created) {
            $this->error('Akun awal atau email sudah tersedia. Perintah dibatalkan.');

            return self::FAILURE;
        }
        $this->info('Super Admin berhasil dibuat. Tidak ada akun atau kata sandi bawaan.');

        return self::SUCCESS;
    }
}
