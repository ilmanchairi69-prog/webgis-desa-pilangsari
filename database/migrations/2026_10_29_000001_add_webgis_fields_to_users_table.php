<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 30)->default('admin_rt');
            });
        }

        if (!Schema::hasColumn('users', 'rt_code')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('rt_code', 20)->nullable();
            });
        }

        if (!Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true);
            });
        }

        if (!Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('last_login_at')->nullable();
            });
        }

        if (Schema::hasColumn('users', 'assigned_rt')) {
            DB::table('users')
                ->whereNull('rt_code')
                ->whereNotNull('assigned_rt')
                ->update(['rt_code' => DB::raw('assigned_rt')]);
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus kolom agar data lama tetap aman.
    }
};