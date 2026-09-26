<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Which real record (Client/Lead/Employee) a document is linked to. Mirrors
     * PhysicalDocumentRelation's Related/RelatedID pair: this column names the
     * kind, the existing LinkedId column carries the record's own ID.
     */
    public function up(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->string('Related', 50)->nullable()->after('DocumentTypeId');
        });
    }

    public function down(): void
    {
        Schema::table('t_Documents', function (Blueprint $table) {
            $table->dropColumn('Related');
        });
    }
};
