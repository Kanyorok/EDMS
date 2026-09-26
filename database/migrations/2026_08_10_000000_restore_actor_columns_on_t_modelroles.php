<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The 2025_10_16_000000_add_modelroleid_to_t_modelroles migration rebuilt
     * t_ModelRoles (to add the ModelRoleId identity PK) using a column list that
     * omitted CreatedBy/ModifiedBy/DeletedBy, silently dropping them even though
     * ModelRole::$fillable and User::syncRolesWithBranch() still write to them.
     * This restores the three columns (nullable, since existing rows have no
     * value to backfill) and their FKs to t_Users.
     */
    public function up(): void
    {
        $sql = <<<'SQL'
IF COL_LENGTH('dbo.t_ModelRoles', 'CreatedBy') IS NULL
BEGIN
    ALTER TABLE dbo.t_ModelRoles ADD CreatedBy BIGINT NULL;
END

IF COL_LENGTH('dbo.t_ModelRoles', 'ModifiedBy') IS NULL
BEGIN
    ALTER TABLE dbo.t_ModelRoles ADD ModifiedBy BIGINT NULL;
END

IF COL_LENGTH('dbo.t_ModelRoles', 'DeletedBy') IS NULL
BEGIN
    ALTER TABLE dbo.t_ModelRoles ADD DeletedBy BIGINT NULL;
END

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_t_ModelRoles_CreatedBy')
BEGIN
    ALTER TABLE dbo.t_ModelRoles ADD CONSTRAINT FK_t_ModelRoles_CreatedBy FOREIGN KEY (CreatedBy) REFERENCES dbo.t_Users(Id);
END

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_t_ModelRoles_ModifiedBy')
BEGIN
    ALTER TABLE dbo.t_ModelRoles ADD CONSTRAINT FK_t_ModelRoles_ModifiedBy FOREIGN KEY (ModifiedBy) REFERENCES dbo.t_Users(Id);
END

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_t_ModelRoles_DeletedBy')
BEGIN
    ALTER TABLE dbo.t_ModelRoles ADD CONSTRAINT FK_t_ModelRoles_DeletedBy FOREIGN KEY (DeletedBy) REFERENCES dbo.t_Users(Id);
END
SQL;

        DB::unprepared($sql);
    }

    public function down(): void
    {
        $sql = <<<'SQL'
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_t_ModelRoles_CreatedBy')
    ALTER TABLE dbo.t_ModelRoles DROP CONSTRAINT FK_t_ModelRoles_CreatedBy;

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_t_ModelRoles_ModifiedBy')
    ALTER TABLE dbo.t_ModelRoles DROP CONSTRAINT FK_t_ModelRoles_ModifiedBy;

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_t_ModelRoles_DeletedBy')
    ALTER TABLE dbo.t_ModelRoles DROP CONSTRAINT FK_t_ModelRoles_DeletedBy;

IF COL_LENGTH('dbo.t_ModelRoles', 'CreatedBy') IS NOT NULL
    ALTER TABLE dbo.t_ModelRoles DROP COLUMN CreatedBy;

IF COL_LENGTH('dbo.t_ModelRoles', 'ModifiedBy') IS NOT NULL
    ALTER TABLE dbo.t_ModelRoles DROP COLUMN ModifiedBy;

IF COL_LENGTH('dbo.t_ModelRoles', 'DeletedBy') IS NOT NULL
    ALTER TABLE dbo.t_ModelRoles DROP COLUMN DeletedBy;
SQL;

        DB::unprepared($sql);
    }
};
