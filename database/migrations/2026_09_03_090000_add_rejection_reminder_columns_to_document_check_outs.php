<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('t_DocumentCheckOuts', static function (Blueprint $table) {
            // Tracks the "check the document back in" reminder emails sent to the document
            // owner while a [REJECTED]-tagged checkout is outstanding. Unrelated to ordinary
            // checkouts, which never carry that tag and so are never touched by the reminder
            // command.
            $table->unsignedTinyInteger('RejectionReminderCount')->default(0)->after('CheckInRemark');
            $table->dateTime('RejectionReminderLastSentOn')->nullable()->after('RejectionReminderCount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_DocumentCheckOuts', static function (Blueprint $table) {
            $table->dropColumn(['RejectionReminderCount', 'RejectionReminderLastSentOn']);
        });
    }
};
