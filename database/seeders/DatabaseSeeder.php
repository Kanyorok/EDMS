<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            $this->call([
                UserSeeder::class,
                BranchSeeder::class,
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->call([
            DepartmentSeeder::class,
            CurrencySeeder::class,
            ModuleSeeder::class,
            RolePermissionSeeder::class,
            WorkFlowTypesSeeder::class,
            DocumentValidationStatusSeeder::class,
            EmployeeSeeder::class,
            DmsLocationTypeRuleSeeder::class,
            DmsRegistrySeeder::class,
            DMSTagsSeeder::class,
            DMSLinkedToTypesSeeder::class,
            DMSDocumentTypesSeeder::class,
            DMSEventTypesSeeder::class,
            RepositorySeeder::class,
            DocumentValidationTypeSeeder::class,
            SystemBankSettingSeeder::class,
        ]);
    }
}
