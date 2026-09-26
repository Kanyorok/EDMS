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
        Schema::table('t_SystemBankSetting', function (Blueprint $table) {
            if (! Schema::hasColumn('t_SystemBankSetting', 'PrimaryColor')) {
                $table->string('PrimaryColor', 7)->nullable()->after('OrganizationLogo');
            }
            if (! Schema::hasColumn('t_SystemBankSetting', 'SidebarBackgroundColor')) {
                $table->string('SidebarBackgroundColor', 7)->nullable()->after('PrimaryColor');
            }
            if (! Schema::hasColumn('t_SystemBankSetting', 'SidebarTextColor')) {
                $table->string('SidebarTextColor', 7)->nullable()->after('SidebarBackgroundColor');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_SystemBankSetting', function (Blueprint $table) {
            foreach (['PrimaryColor', 'SidebarBackgroundColor', 'SidebarTextColor'] as $column) {
                if (Schema::hasColumn('t_SystemBankSetting', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
