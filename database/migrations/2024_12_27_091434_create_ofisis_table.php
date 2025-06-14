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
        Schema::create('ofisis', function (Blueprint $table) {
            $table->id();
            $table->string('jina');
            $table->string('mkoa');
            $table->string('wilaya');
            $table->string('kata');
            $table->boolean('kujiunga_wapya')->default(true);
            $table->longText('maelezo')->nullable();
            $table->enum('status', ['active','inactive','notpaid','closed'])->default('active');
            $table->enum('ainaAcount', ['free', 'paid'])->default('free');
            $table->dateTime('start_day')->default(now());
            $table->dateTime('end_day')->default(now(7));
            $table->dateTime('last_seen')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ofisis');
    }
};
