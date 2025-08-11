<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Notification content
            $table->string('title');
            $table->text('message');
            $table->string('type', 50)->default('info'); // e.g., info, warning, error, sms_balance, kifurushi_expiry
            $table->integer('stage')->default(1); // e.g., 1=reminder, 2=warning, 3=urgent

            // Condition key to identify notification category or trigger
            $table->string('condition_key', 50)->nullable()->index();

            // Optional image URL for notification
            $table->string('image_url')->nullable();

            // Whether notification is active or archived
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes for efficient filtering
            $table->index(['type', 'stage', 'is_active']);
        });

        // PostgreSQL check constraint for 'type' field, optional
        DB::statement("
            ALTER TABLE notifications
            ADD CONSTRAINT notification_type_check
            CHECK (type IN ('info', 'warning', 'error', 'sms_balance', 'kifurushi_expiry'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
