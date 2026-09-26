<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A document's archived state: nullable = active, set = archived.
     * Separate from Visibility (access control, not lifecycle). No history
     * table - the existing activity() log already records every
     * archive/unarchive transition.
     */
    public function up(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->dateTime('ArchivedOn')->nullable()->after('ExpiryDate');
            $table->foreignId('ArchivedBy')->nullable()->after('ArchivedOn')->constrained('t_Users', 'Id');
            $table->string('ArchiveReason', 50)->nullable()->after('ArchivedBy');
        });
    }

    public function down(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ArchivedBy');
            $table->dropColumn(['ArchivedOn', 'ArchiveReason']);
        });
    }
};
