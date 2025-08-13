<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Allow partial index outside transaction
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('kifurushi_id')->constrained()->onDelete('cascade');
            $table->foreignId('ofisi_id')->constrained()->onDelete('cascade'); // ✅ added ofisi_id

            $table->string('reference')->unique();               // ZenoPay order_id
            $table->string('status', 20)->default('pending');    // 'pending', 'completed', 'failed'
            $table->string('transaction_id')->nullable()->index(); // ZenoPay transid
            $table->string('channel')->nullable();               // e.g., MPESA-TZ
            $table->string('phone')->nullable();                 // Buyer phone number
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('sms_amount')->nullable();

            $table->smallInteger('retries_count')->default(0);
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_check_at']);
        });

        // ✅ Partial index for pending payments
        DB::statement("
            CREATE INDEX payments_pending_idx
            ON payments(next_check_at)
            WHERE status = 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
