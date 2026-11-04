<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('facilities')) {
            Schema::create('facilities', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 160);
                $table->string('category', 80);
                $table->text('description')->nullable();
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->string('status', 24)->default('draft')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        // audit_logs sudah ada pada database lama. Tambahkan kolom yang belum tersedia.
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 100)->index();
                $table->string('subject_type', 190)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('before_data')->nullable();
                $table->json('after_data')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        } else {
            $auditColumns = [
                'subject_type' => fn (Blueprint $t) => $t->string('subject_type', 190)->nullable(),
                'subject_id' => fn (Blueprint $t) => $t->unsignedBigInteger('subject_id')->nullable(),
                'before_data' => fn (Blueprint $t) => $t->json('before_data')->nullable(),
                'after_data' => fn (Blueprint $t) => $t->json('after_data')->nullable(),
                'ip_address' => fn (Blueprint $t) => $t->string('ip_address', 45)->nullable(),
                'user_agent' => fn (Blueprint $t) => $t->text('user_agent')->nullable(),
            ];

            foreach ($auditColumns as $column => $definition) {
                if (!Schema::hasColumn('audit_logs', $column)) {
                    Schema::table('audit_logs', function (Blueprint $table) use ($definition): void {
                        $definition($table);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // Tidak menghapus fasilitas, audit, atau data historis.
    }
};