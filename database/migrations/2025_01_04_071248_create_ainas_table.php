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
            $table->integer('riba');
            $table->integer('fomu');
            $table->enum('kipindi_malipo', ['siku', 'wiki', 'mwezi']);
            $table->integer('muda_malipo');
            $table->enum('loan_type', ['kikundi', 'binafsi']);
            $table->foreignId('ofisi_id');
            $table->foreign('ofisi_id')->references('id')->on('ofisis')->onDelete('cascade');
            $table->timestamps();
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
