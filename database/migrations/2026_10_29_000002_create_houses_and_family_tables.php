<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('houses')) {
            Schema::create('houses', function (Blueprint $table): void {
                $table->id();
                $table->string('house_code', 40)->unique();
                $table->string('house_label', 100)->nullable();
                $table->string('rt_code', 20)->nullable()->index();
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('verification_status', 24)->default('pending')->index();
                $table->string('publication_status', 24)->default('draft')->index();
                $table->boolean('public_visible')->default(false);
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->text('rejection_note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        } else {
            $houseColumns = [
                'rt_code' => fn (Blueprint $t) => $t->string('rt_code', 20)->nullable(),
                'latitude' => fn (Blueprint $t) => $t->decimal('latitude', 10, 7)->nullable(),
                'longitude' => fn (Blueprint $t) => $t->decimal('longitude', 10, 7)->nullable(),
                'verification_status' => fn (Blueprint $t) => $t->string('verification_status', 24)->default('pending'),
                'publication_status' => fn (Blueprint $t) => $t->string('publication_status', 24)->default('draft'),
                'public_visible' => fn (Blueprint $t) => $t->boolean('public_visible')->default(false),
                'verified_by' => fn (Blueprint $t) => $t->unsignedBigInteger('verified_by')->nullable(),
                'verified_at' => fn (Blueprint $t) => $t->timestamp('verified_at')->nullable(),
                'published_by' => fn (Blueprint $t) => $t->unsignedBigInteger('published_by')->nullable(),
                'published_at' => fn (Blueprint $t) => $t->timestamp('published_at')->nullable(),
                'rejection_note' => fn (Blueprint $t) => $t->text('rejection_note')->nullable(),
                'archived_at' => fn (Blueprint $t) => $t->timestamp('archived_at')->nullable(),
            ];

            foreach ($houseColumns as $column => $definition) {
                if (!Schema::hasColumn('houses', $column)) {
                    Schema::table('houses', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }

            // Pertahankan relasi legacy, tetapi izinkan rumah baru tanpa map_feature.
            if (Schema::hasColumn('houses', 'map_feature_id')) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE `houses` MODIFY `map_feature_id` BIGINT UNSIGNED NULL');
                } elseif (DB::getDriverName() === 'sqlite') {
                    Schema::table('houses', function (Blueprint $table): void {
                        $table->unsignedBigInteger('map_feature_id')->nullable()->change();
                    });
                }
            }
        }

        if (Schema::hasColumn('houses', 'rt') && Schema::hasColumn('houses', 'rt_code')) {
            DB::table('houses')
                ->whereNull('rt_code')
                ->whereNotNull('rt')
                ->update(['rt_code' => DB::raw('rt')]);
        }

        if (!Schema::hasTable('family_cards')) {
            Schema::create('family_cards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('house_id')->constrained('houses')->cascadeOnDelete();
                $table->text('card_number')->nullable();
                $table->string('head_name', 160)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            if (!Schema::hasColumn('family_cards', 'card_number')) {
                Schema::table('family_cards', function (Blueprint $table): void {
                    $table->text('card_number')->nullable();
                });
            }

            if (!Schema::hasColumn('family_cards', 'head_name')) {
                Schema::table('family_cards', function (Blueprint $table): void {
                    $table->string('head_name', 160)->nullable();
                });
            }

            if (!Schema::hasColumn('family_cards', 'notes')) {
                Schema::table('family_cards', function (Blueprint $table): void {
                    $table->text('notes')->nullable();
                });
            }

            if (Schema::hasColumn('family_cards', 'head_of_family_name')) {
                DB::table('family_cards')
                    ->whereNull('head_name')
                    ->whereNotNull('head_of_family_name')
                    ->update(['head_name' => DB::raw('head_of_family_name')]);
            }
        }

        if (!Schema::hasTable('family_members')) {
            Schema::create('family_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('family_card_id')->constrained('family_cards')->cascadeOnDelete();
                $table->string('full_name', 160);
                $table->string('relationship', 60)->nullable();
                $table->string('gender', 20)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } elseif (!Schema::hasColumn('family_members', 'notes')) {
            Schema::table('family_members', function (Blueprint $table): void {
                $table->text('notes')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Tidak menghapus tabel atau data karena tabel mungkin sudah berisi data lama.
    }
};