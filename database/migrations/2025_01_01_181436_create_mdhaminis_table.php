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
        Schema::create('mdhaminis', function (Blueprint $table) {
            $table->id();

            // Foreign keys using foreignId syntax for cleaner migrations and PostgreSQL compatibility
            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');

            $table->timestamps();

            // Unique constraint to prevent duplicate guarantors on the same loan
            $table->unique(['loan_id', 'customer_id']);

            // Optional: index to speed up queries by customer_id or loan_id
            $table->index('customer_id');
            $table->index('loan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdhaminis');
    }
};
