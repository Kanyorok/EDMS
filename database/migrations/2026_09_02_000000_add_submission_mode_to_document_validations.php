<?php

use App\Enums\DMS\DocumentValidationModeEnum;
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
        Schema::table('t_DocumentValidations', static function (Blueprint $table) {
            // 'va' = validation (approve+sign), 'ap' = approval only, 'av' = approval then validation
            $table->char('SubmissionMode', 2)
                ->default(DocumentValidationModeEnum::Validation->value)
                ->after('Status');
        });

        // Backfill existing rows to the default validation mode.
        DB::table('t_DocumentValidations')->update([
            'SubmissionMode' => DocumentValidationModeEnum::Validation->value,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_DocumentValidations', static function (Blueprint $table) {
            $table->dropColumn('SubmissionMode');
        });
    }
};
