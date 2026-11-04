<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('houses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_feature_id')->unique()
                ->constrained('map_features')->restrictOnDelete();
            $table->string('house_code', 50)->unique();
            $table->string('head_of_family_name', 200);
            $table->string('address', 500);
            $table->string('rt', 30)->index();
            $table->string('rw', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('houses');
    }
};
