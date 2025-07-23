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
        Schema::create('transaction_changes', function (Blueprint $table) {
            $table->id();

            // ENUMs replaced with indexed strings for PostgreSQL flexibility
            $table->string('type', 20)->nullable();          // kuweka, kutoa
            $table->string('category', 20)->nullable();      // fomu, rejesho, pato, tumizi, faini, mkopo
            $table->string('status', 20)->default('pending'); // pending, completed, failed, cancelled
            $table->string('method', 30)->nullable();        // benki, mpesa, halopesa, etc.
            $table->decimal('amount', 20, 2)->nullable();

            $table->longText('description')->nullable();
            $table->longText('admin_details')->nullable();
            $table->longText('reason')->nullable();
            $table->string('action_type', 20)->default('edit'); // edit, delete

            // Foreign keys
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ofisi_id')->constrained('ofisis')->onDelete('cascade');
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');

            $table->timestamps();

            // Composite index for performance
            $table->index(['user_id', 'status']);
        });

        // Optional: Partial index for pending status (faster lookups)
        DB::statement("CREATE INDEX transaction_changes_pending_idx ON transaction_changes (user_id) WHERE status = 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_changes');
    }
};
