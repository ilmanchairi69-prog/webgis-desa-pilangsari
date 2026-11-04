<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Satu bangunan dapat memiliki beberapa KK.
         */
        Schema::create('family_cards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('house_id')
                ->constrained('houses')
                ->cascadeOnDelete();

            $table->string('head_of_family_name', 200);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['house_id', 'head_of_family_name']);
        });

        /*
         * Tambahkan relasi baru secara nullable.
         * Kolom lama tetap dipertahankan untuk kompatibilitas.
         */
        Schema::table('family_members', function (Blueprint $table) {
            $table->foreignId('family_card_id')
                ->nullable()
                ->constrained('family_cards')
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['family_card_id', 'full_name']);
        });

        Schema::table('houses', function (Blueprint $table) {
            $table->string('house_label', 200)->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        /*
         * Migrasikan data lama:
         * satu rumah lama dengan nama kepala keluarga akan
         * memperoleh satu KK awal; anggota lama dipetakan ke KK itu.
         */
        DB::table('houses')
            ->whereNotNull('head_of_family_name')
            ->orderBy('id')
            ->chunk(100, function ($houses) {
                foreach ($houses as $house) {
                    $familyCardId = DB::table('family_cards')->insertGetId([
                        'house_id' => $house->id,
                        'head_of_family_name' => $house->head_of_family_name,
                        'created_at' => $house->created_at ?? now(),
                        'updated_at' => $house->updated_at ?? now(),
                    ]);

                    DB::table('family_members')
                        ->where('house_id', $house->id)
                        ->whereNull('family_card_id')
                        ->update(['family_card_id' => $familyCardId]);
                }
            });
    }

    public function down(): void
    {
        /*
         * Rollback ini menghapus relasi/tabel baru.
         * Jalankan hanya jika aplikasi belum bergantung pada struktur baru.
         */
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropIndex(['family_card_id', 'full_name']);
            $table->dropForeign(['family_card_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'family_card_id',
                'created_by',
                'updated_by',
            ]);
        });

        Schema::table('houses', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'house_label',
                'created_by',
                'updated_by',
            ]);
        });

        Schema::dropIfExists('family_cards');
    }
};