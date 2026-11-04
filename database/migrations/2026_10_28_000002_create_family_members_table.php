<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')
                ->constrained('houses')->cascadeOnDelete();
            $table->string('full_name', 200);
            $table->string('relationship', 50);
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->timestamps();

            $table->index(['house_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
