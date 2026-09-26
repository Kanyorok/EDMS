<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_WorkFlowStages', function (Blueprint $table): void {
            if (! Schema::hasColumn('t_WorkFlowStages', 'OverridePermissionId')) {
                $table->foreignId('OverridePermissionId')->nullable()->constrained('t_Permissions', 'id');
            }
            if (! Schema::hasColumn('t_WorkFlowStages', 'IsActive')) {
                $table->boolean('IsActive')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('t_WorkFlowStages', function (Blueprint $table): void {
            if (Schema::hasColumn('t_WorkFlowStages', 'OverridePermissionId')) {
                $table->dropConstrainedForeignId('OverridePermissionId');
            }
            if (Schema::hasColumn('t_WorkFlowStages', 'IsActive')) {
                $table->dropColumn('IsActive');
            }
        });
    }
};
