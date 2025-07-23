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
        Schema::create('ainas', function (Blueprint $table) {
            $table->id();
            $table->string('jina');
            $table->decimal('riba', 20, 2); // Interest rate
            $table->decimal('fomu', 20, 2); // Form fee

            // Use string instead of ENUM for PostgreSQL
            $table->string('loan_type', 20)->default('binafsi'); // kikundi or binafsi

            // Foreign key
            $table->foreignId('ofisi_id')->constrained('ofisis')->onDelete('cascade');

            $table->timestamps();

            // Indexes for filtering
            $table->index(['ofisi_id', 'loan_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ainas');
    }
};
