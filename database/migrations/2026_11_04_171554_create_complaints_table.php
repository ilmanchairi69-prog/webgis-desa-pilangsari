<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->uuid('submission_key')->unique();
            $table->string('ticket_number', 40)->unique();
            $table->string('name', 120);
            $table->string('phone', 25);
            $table->string('category', 50)->index();
            $table->text('description');
            $table->string('attachment_path')->nullable();
            $table->string('status', 30)->default('new')->index();
            $table->text('admin_response')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
