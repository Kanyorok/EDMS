<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_Branches')) {
            return;
        }

        // SQL Server: CREATE VIEW must be first statement in a batch; use EXEC.
        DB::statement("
            IF OBJECT_ID(N'[dbo].[v_SystemBranchSettingLocal]', N'V') IS NULL
            EXEC('
                CREATE VIEW [dbo].[v_SystemBranchSettingLocal] AS
                SELECT
                    BranchID AS OurBranchID,
                    Name AS BranchName,
                    BranchID,
                    Name,
                    IsHQ
                FROM t_Branches
                WHERE DeletedOn IS NULL
            ')
        ");
    }

    public function down(): void
    {
        DB::statement("
            IF OBJECT_ID(N'[dbo].[v_SystemBranchSettingLocal]', N'V') IS NOT NULL
            DROP VIEW [dbo].[v_SystemBranchSettingLocal]
        ");
    }
};

