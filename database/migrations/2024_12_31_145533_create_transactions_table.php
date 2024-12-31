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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['deposit', 'withdrawal']);
            $table->enum('category', ['ada', 'rejesho','tumizi','faini','mkopo']);
            $table->enum('status', ['pending', 'completed', 'failed']); 
            $table->decimal('amount', 15, 2);
            $table->longText('description')->nullable(); 
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('ofisi_id');
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ofisi_id')->references('id')->on('ofisis')->onDelete('cascade');
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
