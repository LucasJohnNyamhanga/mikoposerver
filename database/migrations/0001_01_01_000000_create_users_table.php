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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('mobile')->unique();
            $table->string('jina_kamili');
            $table->string('jina_mdhamini');
            $table->string('simu_mdhamini');
            $table->string('anakoishi');
            $table->string('picha')->nullable();

            $table->boolean('is_manager')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->string('username')->unique();
            $table->boolean('is_active')->default(false);

            $table->string('password');
            $table->string('fcm_token')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Indexes for common filters (optional)
            $table->index('is_manager');
            $table->index('is_admin');
            $table->index('is_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable()->index(); // index for querying recent tokens
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            // Nullable foreign key for user
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
