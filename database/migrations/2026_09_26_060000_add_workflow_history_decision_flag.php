<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('t_WorkFlowHistory', 'isApproved')) {
            Schema::table('t_WorkFlowHistory', function (Blueprint $table): void {
                $table->boolean('isApproved')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('t_WorkFlowHistory', 'isApproved')) {
            Schema::table('t_WorkFlowHistory', function (Blueprint $table): void {
                $table->dropColumn('isApproved');
            });
        }
    }
};
