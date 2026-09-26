<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('t_WorkFlows', 'MainModuleID')) {
            Schema::table('t_WorkFlows', function (Blueprint $table) {
                $table->unsignedBigInteger('MainModuleID')->nullable()->after('Source');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('t_WorkFlows', 'MainModuleID')) {
            Schema::table('t_WorkFlows', function (Blueprint $table) {
                $table->dropColumn('MainModuleID');
            });
        }
    }
};
