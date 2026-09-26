<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('t_SystemBankSetting')) {
            return;
        }

        Schema::table('t_SystemBankSetting', function (Blueprint $table) {
            if (! Schema::hasColumn('t_SystemBankSetting', 'OrganizationMotto')) {
                $table->string('OrganizationMotto', 200)->nullable()->after('BankRegNumber');
            }

            if (! Schema::hasColumn('t_SystemBankSetting', 'OrganizationLogo')) {
                $table->string('OrganizationLogo', 255)->nullable()->after('OrganizationMotto');
            }

            if (! Schema::hasColumn('t_SystemBankSetting', 'BaseCurrencyID')) {
                $table->unsignedBigInteger('BaseCurrencyID')->nullable()->after('OrganizationLogo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_SystemBankSetting')) {
            return;
        }

        Schema::table('t_SystemBankSetting', function (Blueprint $table) {
            foreach (['BaseCurrencyID', 'OrganizationLogo', 'OrganizationMotto'] as $column) {
                if (Schema::hasColumn('t_SystemBankSetting', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
