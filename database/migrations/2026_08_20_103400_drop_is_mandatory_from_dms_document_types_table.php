<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Removed: it had no effect anywhere in the upload flow and no "required
     * document checklist" feature exists to give it meaning - config with no
     * behaviour behind it.
     */
    public function up(): void
    {
        Schema::table('t_DMSDocumentTypes', function (Blueprint $table) {
            $table->dropColumn('IsMandatory');
        });
    }

    public function down(): void
    {
        Schema::table('t_DMSDocumentTypes', function (Blueprint $table) {
            $table->boolean('IsMandatory')->default(false)->after('MaxSizeMB');
        });
    }
};
