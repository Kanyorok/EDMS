<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Which Linked-to Type(s) a Document Type may legitimately be used
     * against - the constraint the upload form's Linked-to Type dropdown
     * filters by once a Document Type is chosen. Separate from the existing
     * EntityApplicability/t_CodeDetails browse-category filter on
     * t_DMSDocumentTypes, which stays as-is.
     *
     * No soft-deletes here, unlike most DMS tables: this is a pure junction
     * synced wholesale from the admin's multi-select on every save
     * (Eloquent's sync() reads "currently attached" via a raw query that
     * bypasses model scopes, so a soft-deleted row would silently block
     * re-adding the same pairing against the unique index below - simplest
     * correct fix is not to soft-delete a table with no audit value of its
     * own; the Document Type and Linked-to Type master rows keep their own
     * history independently).
     */
    public function up(): void
    {
        Schema::create('t_DMSDocumentTypeLinkedToTypes', static function (Blueprint $table) {
            $table->id('Id');
            $table->foreignId('DocumentTypeId')->constrained('t_DMSDocumentTypes', 'Id');
            $table->foreignId('LinkedToTypeId')->constrained('t_DMSLinkedToTypes', 'Id');
            $table->foreignId('CreatedBy')->constrained('t_Users', 'Id');
            $table->dateTime('CreatedOn');
            $table->foreignId('ModifiedBy')->constrained('t_Users', 'Id');
            $table->dateTime('ModifiedOn');

            $table->unique(['DocumentTypeId', 'LinkedToTypeId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_DMSDocumentTypeLinkedToTypes');
    }
};
