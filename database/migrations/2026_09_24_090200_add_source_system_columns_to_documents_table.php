<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Where a document that came from an external system belongs. Related/LinkedId
     * only describe ERP records, so the external record (a core banking account, loan
     * or application) gets its own columns. (SourceSystemId, ExternalEntityType,
     * ExternalEntityId) is the lookup key. All nullable - ERP-originated documents
     * have none of these.
     */
    public function up(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->foreignId('SourceSystemId')->nullable()->after('LinkedId')
                ->constrained('t_DMSSourceSystems', 'Id');
            $table->foreignId('EventTypeId')->nullable()->after('SourceSystemId')
                ->constrained('t_DMSEventTypes', 'Id');
            $table->unsignedInteger('SourceModuleId')->nullable()->after('EventTypeId');
            $table->string('ExternalEntityType', 50)->nullable()->after('SourceModuleId');
            $table->string('ExternalEntityId', 100)->nullable()->after('ExternalEntityType');

            $table->index(['SourceSystemId', 'ExternalEntityType', 'ExternalEntityId'], 'IX_t_Documents_ExternalEntity');
        });
    }

    public function down(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->dropIndex('IX_t_Documents_ExternalEntity');
            $table->dropConstrainedForeignId('SourceSystemId');
            $table->dropConstrainedForeignId('EventTypeId');
            $table->dropColumn(['SourceModuleId', 'ExternalEntityType', 'ExternalEntityId']);
        });
    }
};
