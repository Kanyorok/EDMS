<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Which live-record lookup (DocumentRelationOptionsController::RELATION_TYPES
     * key - Client/Lead/Employee today) a Linked-to Type's Link Record search
     * should be scoped to. Nullable: types with no live lookup wired up yet
     * (Collateral, Vehicle, ...) keep the upload form's manual "Related to"
     * picker.
     */
    public function up(): void
    {
        Schema::table('t_DMSLinkedToTypes', static function (Blueprint $table) {
            $table->string('SourceRelation', 50)->nullable()->after('Description');
        });
    }

    public function down(): void
    {
        Schema::table('t_DMSLinkedToTypes', static function (Blueprint $table) {
            $table->dropColumn('SourceRelation');
        });
    }
};
