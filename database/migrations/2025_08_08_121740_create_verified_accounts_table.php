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
        Schema::create('verified_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('kifurushi_id')->constrained()->onDelete('cascade');
            $table->foreignId('ofisi_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('ofisi_changes_count')->default(0);
            $table->unsignedInteger('ofisi_creation_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'kifurushi_id', 'ofisi_id']);
            $table->unique(['user_id', 'ofisi_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verified_accounts');
    }
};
