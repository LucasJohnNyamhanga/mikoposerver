<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // 🔐 Required to allow raw index outside of transaction
    public $withinTransaction = false;

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

            $table->string('status', 20)->default('pending')->index(); // e.g., pending, approved, expired
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->string('reference')->nullable()->unique(); // Unique external ref (e.g. ZenoPay)

            $table->timestamps();

            // 🔍 Composite index to filter by user + status
            $table->index(['user_id', 'status']);
        });

        // ✅ PostgreSQL partial index for approved + active purchases
        DB::statement("
            CREATE INDEX kifurushi_active_pending_idx
            ON kifurushi_purchases (user_id)
            WHERE status = 'approved' AND is_active = true
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
