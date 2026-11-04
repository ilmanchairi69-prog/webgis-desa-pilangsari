<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_layers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->enum('layer_type', ['point', 'line', 'polygon']);
            $table->string('color', 7)->default('#16803c');
            $table->boolean('is_public')->default(false);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('source_name')->nullable();
            $table->text('source_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('map_layers'); }
};
