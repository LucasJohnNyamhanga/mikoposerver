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
        Schema::create('dhamanas', function (Blueprint $table) {
            $table->id();
            $table->string('jina');
            $table->decimal('thamani', 20, 2);
            $table->text('maelezo')->nullable();
            $table->string('picha')->nullable();
            
            // Ownership & custody flags
            $table->boolean('is_ofisi_owned')->default(false); // TRUE = asset belongs to office
            $table->boolean('is_sold')->default(false);
            
            // Custody: who holds the dhamana physically
            $table->enum('stored_at', ['ofisi', 'customer'])->default('ofisi');
        
            $table->timestamps();
        
            // Foreign keys
            $table->foreignId('loan_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('cascade'); // who owns the dhamana
            $table->foreignId('ofisi_id')->constrained()->onDelete('cascade'); // where dhamana is managed
        });        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dhamanas');
    }
};
