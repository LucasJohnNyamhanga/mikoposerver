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
            $table->integer('thamani');
            $table->string('maelezo');
            $table->string('picha');
            $table->boolean('is_ofisi_owned')->default(false);
            $table->boolean('is_sold')->default(false);
            $table->timestamps();

            // Foreign keys
            $table->foreignId('loan_id')->nullable();
            $table->foreignId('customer_id')->nullable();
            $table->foreignId('ofisi_id');
            
            // Define foreign key constraints
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('ofisi_id')->references('id')->on('ofisis')->onDelete('cascade');
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
