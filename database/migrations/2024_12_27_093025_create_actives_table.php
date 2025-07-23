<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('actives', function (Blueprint $table) {
            $table->id();

            // Use foreignId for clearer and PostgreSQL-compatible foreign keys
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ofisi_id')->constrained('ofisis')->onDelete('cascade');

            $table->timestamps();

            // Indexes to speed up common queries
            $table->index('user_id');
            $table->index('ofisi_id');

            // Optional: unique constraint if a user can only be active once per office
            $table->unique(['user_id', 'ofisi_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actives');
    }
};
