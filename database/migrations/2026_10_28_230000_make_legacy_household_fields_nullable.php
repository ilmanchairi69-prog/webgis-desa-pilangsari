<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('houses', function (Blueprint $table) {
            $table->string('head_of_family_name', 200)->nullable()->change();
            $table->string('address', 500)->nullable()->change();
            $table->string('rt', 30)->nullable()->change();
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->unsignedBigInteger('house_id')->nullable()->change();
            $table->string('relationship', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak mengembalikan kolom menjadi wajib isi secara otomatis.
        // Kolom-kolom ini mungkin sudah NULL setelah struktur baru digunakan.
    }
};