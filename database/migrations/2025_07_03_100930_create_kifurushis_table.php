<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kifurushis', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('muda', 20)->nullable(); // 'siku', 'wiki', 'mwezi'

            $table->integer('number_of_offices')->default(1);
            $table->integer('duration_in_days')->default(30);

            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->text('offer')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['is_active']);
            $table->index(['is_active', 'is_popular']); // For filters
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kifurushis');
    }
};
