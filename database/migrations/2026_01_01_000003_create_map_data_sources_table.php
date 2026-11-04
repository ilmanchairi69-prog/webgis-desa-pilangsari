<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('source_type', 40);
            $table->text('description')->nullable();
            $table->text('license_notes')->nullable();
            $table->date('acquired_at')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('map_data_sources'); }
};
