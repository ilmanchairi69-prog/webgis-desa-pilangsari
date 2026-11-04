<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_visit_counts', function (Blueprint $table): void {
            $table->date('visit_date')->primary();
            $table->unsignedBigInteger('visits')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_visit_counts');
    }
};
