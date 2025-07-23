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
        Schema::create('user_ofisis', function (Blueprint $table) {
            $table->id();

            // Use foreignId for cleaner foreign keys
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ofisi_id')->constrained('ofisis')->onDelete('cascade');
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();

            // Replace enum with string for PostgreSQL flexibility
            $table->string('status', 20)->default('pending'); // pending, accepted, denied

            $table->boolean('isActive')->default(false);
            $table->timestamps();

            // Indexes for performance on common filters
            $table->index('user_id');
            $table->index('ofisi_id');
            $table->index('status');
            $table->index('isActive');

            // Optional unique constraint if user can only have one position per office
            // $table->unique(['user_id', 'ofisi_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_ofisis');
    }
};
