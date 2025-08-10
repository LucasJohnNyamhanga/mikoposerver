<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ofisi_id')->constrained()->onDelete('cascade');

            $table->unsignedInteger('allowed_sms')->default(0);
            $table->unsignedInteger('used_sms')->default(0);

            $table->date('start_date');
            $table->date('expires_at');

            $table->string('status', 20)->default('pending');
            $table->string('sender_id')->nullable();
            $table->string('phone')->nullable();

            $table->timestamps();

            // 🧠 Constraints & Indexes
            $table->unique(['user_id', 'ofisi_id']);
            $table->index(['status', 'expires_at']);
            $table->index('start_date');
            $table->index('user_id');
            $table->index('ofisi_id');
        });

        // PostgreSQL-compatible enum constraint
        DB::statement("ALTER TABLE sms_balances ADD CONSTRAINT sms_status_check CHECK (status IN ('active', 'expired', 'pending'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_balances');
    }
};
