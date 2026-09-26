<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who withdrew an approved file request, and why.
 *
 * Cancellation is not a second decision: the approval still happened and the
 * requester was told of it. Writing the withdrawal over the decision columns
 * would erase the very approval it undoes, so it is recorded beside them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_DMSPhysicalFileRequests')
            || Schema::hasColumn('t_DMSPhysicalFileRequests', 'CancelledOn')) {
            return;
        }

        Schema::table('t_DMSPhysicalFileRequests', function (Blueprint $table) {
            $table->foreignId('CancelledByUserId')->nullable()->constrained('t_Users', 'Id');
            $table->dateTime('CancelledOn')->nullable();
            $table->string('CancellationReason', 1000)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('t_DMSPhysicalFileRequests', 'CancelledOn')) {
            return;
        }

        Schema::table('t_DMSPhysicalFileRequests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('CancelledByUserId');
            $table->dropColumn(['CancelledOn', 'CancellationReason']);
        });
    }
};
