<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create ENUM types first (PostgreSQL requirement)
        DB::statement("CREATE TYPE muda_enum AS ENUM ('siku', 'wiki', 'mwezi')");
        DB::statement("CREATE TYPE kifurushi_type_enum AS ENUM ('kifurushi', 'sms')");

        Schema::create('kifurushis', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->enum('muda', ['siku', 'wiki', 'mwezi'])->nullable()->default(null)
                ->comment('Duration type');

            $table->integer('number_of_offices')->default(1);
            $table->integer('duration_in_days')->default(30);

            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->text('offer')->nullable();
            $table->boolean('special')->default(false);
            $table->enum('type', ['kifurushi', 'sms'])->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_active']);
            $table->index(['is_active', 'is_popular']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kifurushis');
        DB::statement("DROP TYPE IF EXISTS muda_enum");
        DB::statement("DROP TYPE IF EXISTS kifurushi_type_enum");
    }
};
