<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Removed: Face/Signature validation was config with no automatic check
     * behind it - nothing in the upload flow ever read it. Not needed for now.
     */
    public function up(): void
    {
        Schema::table('t_DMSDocumentTypes', function (Blueprint $table) {
            $table->dropColumn('Validation');
        });
    }

    public function down(): void
    {
        Schema::table('t_DMSDocumentTypes', function (Blueprint $table) {
            $table->char('Validation', 2)->nullable()->after('TagId')->comment('DocumentTypeValidationEnum - no,fc,sg');
        });
    }
};
