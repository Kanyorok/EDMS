<?php

use App\Enums\DMS\DocumentValidationStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('t_DocumentValidationTypes', static function (Blueprint $table) {
            // Null = no workflow bound; the type keeps the legacy single-approval behaviour.
            $table->foreignId('WorkFlowId')->nullable()->after('Visibility')->constrained('t_Workflows', 'Id');
        });

        Schema::table('t_DocumentValidations', static function (Blueprint $table) {
            $table->char('Status', 2)->default(DocumentValidationStatusEnum::Pending->value)->after('DocumentId');
            $table->foreignId('RejectedBy')->nullable()->after('ApprovedOn')->constrained('t_Users', 'Id');
            $table->dateTime('RejectedOn')->nullable()->after('RejectedBy');
            $table->text('RejectionReason')->nullable()->after('RejectedOn');
        });

        // Backfill. Note that rows rejected before this change also carry ApprovedBy (the old reject()
        // stamped ApprovedBy/ApprovedOn exactly like approve did), so they backfill as approved. Where
        // that distinction matters, the activity log holds the truth: entries with event = 'reject' on
        // subject_type 'DocumentValidationId' identify the rows that should be corrected to 're'.
        DB::table('t_DocumentValidations')
            ->whereNotNull('ApprovedBy')
            ->update(['Status' => DocumentValidationStatusEnum::Approved->value]);

        DB::table('t_DocumentValidations')
            ->whereNull('ApprovedBy')
            ->update(['Status' => DocumentValidationStatusEnum::Pending->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_DocumentValidations', static function (Blueprint $table) {
            $table->dropForeign(['RejectedBy']);
            $table->dropColumn(['Status', 'RejectedBy', 'RejectedOn', 'RejectionReason']);
        });

        Schema::table('t_DocumentValidationTypes', static function (Blueprint $table) {
            $table->dropForeign(['WorkFlowId']);
            $table->dropColumn('WorkFlowId');
        });
    }
};
