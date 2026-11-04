<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layer_id')->constrained('map_layers')->restrictOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->json('geometry')->nullable();
            $table->json('properties')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->string('source_name')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->enum('publication_status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['verification_status', 'publication_status']);
        });
    }

    public function down(): void { Schema::dropIfExists('map_features'); }
};
