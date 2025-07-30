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
        Schema::create('kifurushis', function (Blueprint $table) {
            $table->id(); // PostgreSQL uses BIGSERIAL for IDs by default
            $table->string('name')->unique(); // Unique index automatically created
            $table->text('description')->nullable();

            // PostgreSQL does not support UNSIGNED, so use normal integers
            $table->integer('number_of_offices')->default(1);  
            $table->integer('duration_in_days')->default(30);   

            $table->decimal('price', 10, 2)->default(0);  // Price field
            $table->boolean('is_active')->default(true);  // Indicates if package is active
            $table->text('offer')->nullable();
            $table->timestamps();

            // Index to quickly filter active packages
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kifurushis');
    }
};
