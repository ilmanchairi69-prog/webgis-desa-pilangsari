<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_categories', function (Blueprint $table): void {
            $table->string('system_key', 32)->primary();
            $table->foreignId('source_layer_id')->nullable()->unique()->constrained('map_layers')->restrictOnDelete();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
        foreach (['houses', 'facilities'] as $key) {
            DB::table('publication_categories')->insert(['system_key' => $key, 'is_public' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_categories');
    }
};
