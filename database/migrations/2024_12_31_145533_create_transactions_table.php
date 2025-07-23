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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Replace ENUMs with string columns (more flexible for PostgreSQL)
            $table->string('type', 20);        // 'kuweka', 'kutoa'
            $table->string('category', 20);    // 'fomu', 'rejesho', 'pato', 'tumizi', 'faini', 'mkopo'
            $table->string('status', 20)->default('pending'); // 'pending', 'completed', 'failed', 'cancelled'
            $table->string('method', 30)->nullable();        // 'benki', 'mpesa', etc.

            $table->decimal('amount', 20, 2);
            $table->longText('description')->nullable();

            $table->boolean('edited')->default(false);
            $table->boolean('is_loan_source')->default(false);

            // Foreign keys using foreignId for cleaner syntax & PostgreSQL compatibility
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ofisi_id')->constrained('ofisis')->onDelete('cascade');
            $table->foreignId('loan_id')->nullable()->constrained('loans')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('cascade');

            $table->timestamps();

            // Indexes to speed up common queries
            $table->index(['user_id', 'status']);
            $table->index('loan_id');
            $table->index('customer_id');
            $table->index('ofisi_id');
        });

        // Optional: Partial index for pending status for faster lookups
        DB::statement("CREATE INDEX transactions_pending_idx ON transactions (user_id) WHERE status = 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
