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
        Schema::create('mabadilikos', function (Blueprint $table) {
            $table->id();

            // Use foreignId for cleaner and PostgreSQL-compatible foreign keys
            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');

            $table->string('action');
            $table->text('description');
            $table->timestamps();

            // Optional indexes for frequent queries
            $table->index('loan_id');
            $table->index('performed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mabadilikos');
    }
};
