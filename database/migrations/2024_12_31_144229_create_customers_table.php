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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('jina');
            $table->string('jinaMaarufu');
            $table->string('jinsia');
            $table->string('anapoishi');
            $table->string('simu')->unique();
            $table->string('kazi');
            $table->string('picha');

            // Foreign keys
            $table->foreignId('ofisi_id')->constrained('ofisis')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');

            $table->timestamps();

            // Indexes to speed up common queries
            $table->index('ofisi_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
