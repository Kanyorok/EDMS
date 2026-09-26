<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * What a document was uploaded against: the business entity it's linked to,
     * the document type whose rules governed the upload, and the folio batching
     * files uploaded together under one context. All nullable - older documents
     * were uploaded before these existed, so nothing here can be a hard NOT NULL.
     */
    public function up(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->foreignId('DocumentTypeId')->nullable()->after('RepositoryId')
                ->constrained('t_DMSDocumentTypes', 'Id');
            $table->foreignId('LinkedToTypeId')->nullable()->after('DocumentTypeId')
                ->constrained('t_DMSLinkedToTypes', 'Id');
            $table->string('LinkedId', 100)->nullable()->after('LinkedToTypeId');
            // Not unique: a folio batches several files uploaded together under one
            // context, so more than one document legitimately shares the same folio.
            $table->string('Folio', 100)->nullable()->after('LinkedId');
            $table->text('Notes')->nullable()->after('Folio');
        });
    }

    public function down(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('DocumentTypeId');
            $table->dropConstrainedForeignId('LinkedToTypeId');
            $table->dropColumn(['LinkedId', 'Folio', 'Notes']);
        });
    }
};
