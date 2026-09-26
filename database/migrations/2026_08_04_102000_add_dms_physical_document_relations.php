<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('t_DMSPhysicalDocuments', 'Notes')
            && ! Schema::hasColumn('t_DMSPhysicalDocuments', 'Description')) {
            Schema::table('t_DMSPhysicalDocuments', function (Blueprint $table) {
                $table->renameColumn('Notes', 'Description');
            });
        }

        if (Schema::hasTable('t_DMSPhysicalDocumentRelations')) {
            return;
        }

        Schema::create('t_DMSPhysicalDocumentRelations', function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('PhysicalDocumentId')->constrained('t_DMSPhysicalDocuments', 'Id');
            $table->string('Related', 100);
            $table->string('RelatedID', 100);
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');
            $table->foreignId('DeletedBy')->nullable()->constrained('t_Users', 'Id');
            $table->softDeletes('DeletedOn');

            $table->index(['Related', 'RelatedID'], 'IX_DMSPhysicalDocumentRelations_Related');
            $table->index('PhysicalDocumentId', 'IX_DMSPhysicalDocumentRelations_Document');
        });

        DB::statement('
            CREATE UNIQUE INDEX UX_DMSPhysicalDocumentRelations_Link
            ON t_DMSPhysicalDocumentRelations (PhysicalDocumentId, Related, RelatedID)
            WHERE DeletedOn IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('t_DMSPhysicalDocumentRelations');

        if (Schema::hasColumn('t_DMSPhysicalDocuments', 'Description')
            && ! Schema::hasColumn('t_DMSPhysicalDocuments', 'Notes')) {
            Schema::table('t_DMSPhysicalDocuments', function (Blueprint $table) {
                $table->renameColumn('Description', 'Notes');
            });
        }
    }
};
