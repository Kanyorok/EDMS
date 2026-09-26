<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_UserNotifications', function (Blueprint $table) {
            $table->bigIncrements('Id');
            $table->unsignedBigInteger('UserID')->index(); // t_Users.Id
            $table->string('Type', 80)->nullable();
            $table->string('Title', 200);
            $table->text('Body')->nullable();
            $table->string('Source', 120)->nullable();
            $table->string('SourceID', 120)->nullable();
            $table->timestamp('ReadOn')->nullable();
            $table->timestamp('CreatedOn')->useCurrent();

            $table->foreign('UserID')->references('Id')->on('t_Users')->cascadeOnDelete();
        });

        Schema::create('t_NotificationDispatchLogs', function (Blueprint $table) {
            $table->bigIncrements('Id');
            $table->string('Channel', 30); // inapp|email|sms
            $table->string('Type', 120);   // logical event type
            $table->string('Key', 200);    // idempotency key (type-specific)
            $table->unsignedBigInteger('CreatedBy')->nullable();
            $table->timestamp('CreatedOn')->useCurrent();

            $table->unique(['Channel', 'Key'], 'ux_notification_dispatch_channel_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_NotificationDispatchLogs');
        Schema::dropIfExists('t_UserNotifications');
    }
};

