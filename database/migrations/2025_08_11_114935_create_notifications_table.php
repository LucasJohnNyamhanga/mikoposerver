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

            $table->string('title');
            $table->text('message');
            $table->string('type', 50)->default('tip'); // 'tip' includes info, warning, error; others: sms, vifurushi
            $table->integer('stage')->default(1); // 1=reminder, 2=warning, 3=urgent

            $table->string('condition_key', 50)->nullable()->index();

            $table->string('image_url')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['type', 'stage', 'is_active']);
        });

        // PostgreSQL CHECK constraint with combined tips
        DB::statement("
            ALTER TABLE notifications
            ADD CONSTRAINT notification_type_check
            CHECK (type IN (
                'tip',        -- combined info, warning, error
                'sms',
                'vifurushi'
            ))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
