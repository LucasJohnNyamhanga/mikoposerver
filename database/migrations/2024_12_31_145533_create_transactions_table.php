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
        $table->enum('type', ['kuweka', 'kutoa']);
        $table->enum('category', ['fomu', 'rejesho','pato', 'tumizi', 'faini', 'mkopo',]);
        $table->enum('status', ['pending', 'completed', 'failed']);
        $table->enum('method', ['benki', 'mpesa', 'halopesa','airtelmoney','mix by yas','pesa mkononi'])->nullable();
        $table->decimal('amount', 20, 2);
        $table->longText('description')->nullable();

        // Foreign keys
        $table->unsignedBigInteger('created_by');
        $table->unsignedBigInteger('approved_by')->nullable();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('ofisi_id');
        $table->unsignedBigInteger('loan_id')->nullable();
        $table->unsignedBigInteger('customer_id')->nullable();

        $table->timestamps();

        // Foreign key constraints
        $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('approved_by')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('ofisi_id')->references('id')->on('ofisis')->onDelete('cascade');
        $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
        $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
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
