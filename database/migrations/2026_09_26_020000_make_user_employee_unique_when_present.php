<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS t_users_employeeid_unique ON t_Users');
        DB::statement('CREATE UNIQUE INDEX t_users_employeeid_unique ON t_Users (EmployeeId) WHERE EmployeeId IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS t_users_employeeid_unique ON t_Users');
    }
};
