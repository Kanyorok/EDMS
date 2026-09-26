<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * An event is a business occasion in an external system that produces a document:
     * it names the document type whose upload rules apply, the kind of external record
     * the file belongs to, the screens (module IDs in that system) that can raise it, and
     * the properties the user must supply. The external system renders its upload form
     * from these rows, so a new property never needs a change on the external side.
     */
    public function up(): void
    {
        Schema::create('t_DMSEventTypes', static function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('SourceSystemId')->constrained('t_DMSSourceSystems', 'Id');
            $table->string('Code', 50);
            $table->string('Name', 150);
            $table->string('Description', 255)->nullable();
            $table->foreignId('DocumentTypeId')->constrained('t_DMSDocumentTypes', 'Id');
            $table->foreignId('LinkedToTypeId')->nullable()->constrained('t_DMSLinkedToTypes', 'Id');
            // What the file hangs off in the external system, e.g. ACCOUNT, LOAN, APPLICATION.
            $table->string('ExternalEntityType', 50);
            $table->boolean('AllowReplace')->default(true);
            $table->boolean('IsActive')->default(true);
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');

            $table->unique(['SourceSystemId', 'Code']);
        });

        Schema::create('t_DMSEventModuleMaps', static function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('EventTypeId')->constrained('t_DMSEventTypes', 'Id');
            // The module/screen identifier in the external system (CBS ModuleID).
            $table->unsignedInteger('SourceModuleId');
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');

            $table->unique(['EventTypeId', 'SourceModuleId']);
            $table->index('SourceModuleId');
        });

        Schema::create('t_DMSEventProperties', static function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('EventTypeId')->constrained('t_DMSEventTypes', 'Id');
            // Stored as the attribute name on the document, e.g. cbs.received_by.
            $table->string('Name', 100);
            $table->string('Label', 150);
            // DataTypesEnum value: st, it, ft, bo, dt, tm, ls.
            $table->string('DataType', 2)->default('st');
            $table->boolean('IsRequired')->default(false);
            // JSON [{"value":"IDEN","label":"Identification"}] for list properties.
            $table->text('Options')->nullable();
            $table->unsignedSmallInteger('SortOrder')->default(0);
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');

            $table->unique(['EventTypeId', 'Name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_DMSEventProperties');
        Schema::dropIfExists('t_DMSEventModuleMaps');
        Schema::dropIfExists('t_DMSEventTypes');
    }
};
