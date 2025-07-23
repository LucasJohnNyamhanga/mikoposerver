<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ofisis', function (Blueprint $table) {
            $table->id();

            $table->string('jina');
            $table->string('mkoa');
            $table->string('wilaya');
            $table->string('kata');

            $table->boolean('kujiunga_wapya')->default(true);
            $table->longText('maelezo')->nullable();

            // Replace enums with strings for flexibility
            $table->string('status', 20)->default('active');       // active, inactive, notpaid, closed
            $table->string('ainaAcount', 20)->default('free');     // free, paid

            // Default timestamps with PostgreSQL expressions
            $table->dateTime('start_day')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('end_day')->default(DB::raw("(CURRENT_TIMESTAMP + INTERVAL '7 day')"));

            $table->dateTime('last_seen')->nullable();

            $table->timestamps();

            // Add indexes for common queries
            $table->index('status');
            $table->index('ainaAcount');
            $table->index('start_day');
            $table->index('end_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ofisis');
    }
};
