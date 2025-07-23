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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();  // PostgreSQL uses BIGSERIAL by default for primary keys
            $table->foreignId('kifurushi_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('reference')->unique();               // ZenoPay order_id
            $table->string('status', 20)->default('pending');    // 'pending', 'completed', 'failed'
            $table->string('transaction_id')->nullable()->index(); // ZenoPay transid
            $table->string('channel')->nullable();               // e.g., MPESA-TZ
            $table->string('phone')->nullable();                 // Buyer phone number
            $table->decimal('amount', 12, 2);                    // PostgreSQL handles decimal well

            $table->smallInteger('retries_count')->default(0);   // unsignedTinyInteger replacement
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('paid_at')->nullable();            // Optional timestamp
            $table->timestamps();

            // Composite index for faster queries
            $table->index(['status', 'next_check_at']);
        });

        // Partial index optimization for 'pending' payments
        DB::statement("CREATE INDEX payments_pending_idx ON payments(next_check_at) WHERE status = 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
