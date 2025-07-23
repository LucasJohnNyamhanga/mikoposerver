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
        Schema::create('kifurushi_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('kifurushi_id')->constrained()->onDelete('cascade');
            $table->foreignId('ofisi_id')->constrained()->onDelete('cascade');

            // Instead of ENUM, use string for PostgreSQL
            $table->string('status', 20)->default('pending')->index(); 
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('approved_at')->nullable();
            
            $table->string('reference')->nullable()->unique(); // Unique reference number
            $table->timestamps();

            // Composite index for filtering by user and status
            $table->index(['user_id', 'status']);
        });

        // Partial index for faster queries on active and pending purchases
        DB::statement("
            CREATE INDEX kifurushi_active_pending_idx 
            ON kifurushi_purchases (user_id) 
            WHERE status = 'pending' AND is_active = true
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kifurushi_purchases');
    }
};
