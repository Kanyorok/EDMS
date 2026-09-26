<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('t_DashboardWidgets')) {
            Schema::table('t_DashboardWidgets', function (Blueprint $table) {
                if (! Schema::hasColumn('t_DashboardWidgets', 'RequiredPermissions')) {
                    $table->json('RequiredPermissions')->nullable()->after('DefaultFilters');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('t_DashboardWidgets')) {
            Schema::table('t_DashboardWidgets', function (Blueprint $table) {
                if (Schema::hasColumn('t_DashboardWidgets', 'RequiredPermissions')) {
                    $table->dropColumn('RequiredPermissions');
                }
            });
        }
    }
};

