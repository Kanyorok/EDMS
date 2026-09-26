<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_SystemBackupSettings', function (Blueprint $table) {
            $table->id('Id');
            $table->string('BackupPath', 500);
            $table->string('FileNamePrefix', 100)->nullable();
            $table->boolean('ScheduleEnabled')->default(false);
            $table->time('ScheduleTime')->nullable();
            $table->dateTime('LastRunAt')->nullable();
            $table->unsignedBigInteger('CreatedBy')->nullable();
            $table->dateTime('CreatedOn')->nullable();
            $table->unsignedBigInteger('ModifiedBy')->nullable();
            $table->dateTime('ModifiedOn')->nullable();
        });

        Schema::create('t_SystemBackupRuns', function (Blueprint $table) {
            $table->id('Id');
            $table->unsignedBigInteger('BackupSettingID')->nullable();
            $table->string('BackupType', 30);
            $table->string('Status', 30);
            $table->string('DatabaseName', 200);
            $table->string('BackupPath', 500);
            $table->string('BackupFile', 700);
            $table->text('Message')->nullable();
            $table->dateTime('StartedOn')->nullable();
            $table->dateTime('FinishedOn')->nullable();
            $table->unsignedBigInteger('RequestedBy')->nullable();
            $table->dateTime('CreatedOn')->nullable();

            $table->index(['Status', 'StartedOn']);
            $table->index(['BackupType', 'StartedOn']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_SystemBackupRuns');
        Schema::dropIfExists('t_SystemBackupSettings');
    }
};
