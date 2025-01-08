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
            $table->decimal('riba',20, 2)->default(10);
            $table->decimal('fomu', 20, 2)->default(10);
            $table->decimal('total_due', 20, 2)->nullable();
            $table->enum('status', ['pending','waiting','error','approved','repaid','defaulted','closed'])->default('pending');
            $table->enum('kipindi_malipo', ['siku', 'wiki', 'mwezi', 'mwaka']);
            $table->enum('loan_type', ['kikundi', 'binafsi']);
            $table->longText('jina_kikundi')->nullable();
            $table->integer('muda_malipo')->nullable();
            $table->dateTime('issued_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->longText('status_details')->nullable();
            $table->foreignId('ofisi_id');
            $table->foreignId('user_id');
            $table->timestamps();
            
            $table->foreign('ofisi_id')->references('id')->on('ofisis')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
