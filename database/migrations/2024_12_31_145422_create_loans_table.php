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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 20, 2);
            $table->decimal('riba', 20, 2)->default(10);
            $table->decimal('fomu', 20, 2)->default(10);
            $table->decimal('total_due', 20, 2)->nullable();

            // Replace enums with strings for flexibility in PostgreSQL
            $table->string('status', 20)->default('pending'); // pending, waiting, error, approved, repaid, defaulted, closed
            $table->string('kipindi_malipo', 20);             // siku, wiki, mwezi
            $table->string('loan_type', 20);                   // kikundi, binafsi

            $table->longText('jina_kikundi')->nullable();
            $table->integer('muda_malipo')->nullable();
            $table->dateTime('issued_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->longText('status_details')->nullable();

            // Foreign keys using foreignId for clarity and compatibility
            $table->foreignId('ofisi_id')->constrained('ofisis')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->timestamps();

            // Indexes for frequent queries
            $table->index(['status']);
            $table->index(['ofisi_id']);
            $table->index(['user_id']);
            $table->index(['loan_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
