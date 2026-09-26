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
        if (! Schema::hasColumn('t_Modules', 'IsActive')) {
            Schema::table('t_Modules', function (Blueprint $table) {
                $table->boolean('IsActive')->default(true)->after('RequiredPermission');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('t_Modules', 'IsActive')) {
            Schema::table('t_Modules', function (Blueprint $table) {
                $table->dropColumn('IsActive');
            });
        }
    }
};
