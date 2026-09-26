<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('activitylog.table_name', 't_SYSActivityLog');

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'ModuleID')) {
                $blueprint->unsignedBigInteger('ModuleID')->nullable();
            }
            if (! Schema::hasColumn($table, 'UserName')) {
                $blueprint->string('UserName', 150)->nullable();
            }
            if (! Schema::hasColumn($table, 'RoleID')) {
                $blueprint->unsignedBigInteger('RoleID')->nullable();
            }
            if (! Schema::hasColumn($table, 'RoleName')) {
                $blueprint->string('RoleName', 150)->nullable();
            }
            if (! Schema::hasColumn($table, 'ModuleName')) {
                $blueprint->string('ModuleName', 100)->nullable();
            }
            if (! Schema::hasColumn($table, 'FeatureName')) {
                $blueprint->string('FeatureName', 150)->nullable();
            }
            if (! Schema::hasColumn($table, 'ActionType')) {
                $blueprint->string('ActionType', 50)->nullable();
            }
            if (! Schema::hasColumn($table, 'EntityName')) {
                $blueprint->string('EntityName', 150)->nullable();
            }
            if (! Schema::hasColumn($table, 'TableName')) {
                $blueprint->string('TableName', 150)->nullable();
            }
            if (! Schema::hasColumn($table, 'RecordID')) {
                $blueprint->string('RecordID', 100)->nullable();
            }
            if (! Schema::hasColumn($table, 'ReferenceNo')) {
                $blueprint->string('ReferenceNo', 100)->nullable();
            }
            if (! Schema::hasColumn($table, 'DescriptionText')) {
                $blueprint->longText('DescriptionText')->nullable();
            }
            if (! Schema::hasColumn($table, 'OldValues')) {
                $blueprint->longText('OldValues')->nullable();
            }
            if (! Schema::hasColumn($table, 'NewValues')) {
                $blueprint->longText('NewValues')->nullable();
            }
            if (! Schema::hasColumn($table, 'ChangedFields')) {
                $blueprint->longText('ChangedFields')->nullable();
            }
            if (! Schema::hasColumn($table, 'IPAddress')) {
                $blueprint->string('IPAddress', 50)->nullable();
            }
            if (! Schema::hasColumn($table, 'UserAgent')) {
                $blueprint->longText('UserAgent')->nullable();
            }
            if (! Schema::hasColumn($table, 'DeviceName')) {
                $blueprint->string('DeviceName', 150)->nullable();
            }
            if (! Schema::hasColumn($table, 'SessionID')) {
                $blueprint->string('SessionID', 150)->nullable();
            }
            if (! Schema::hasColumn($table, 'RequestID')) {
                $blueprint->string('RequestID', 150)->nullable();
            }
            if (! Schema::hasColumn($table, 'Status')) {
                $blueprint->string('Status', 30)->nullable();
            }
            if (! Schema::hasColumn($table, 'FailureReason')) {
                $blueprint->longText('FailureReason')->nullable();
            }
            if (! Schema::hasColumn($table, 'CreatedBy')) {
                $blueprint->unsignedBigInteger('CreatedBy')->nullable();
            }
            if (! Schema::hasColumn($table, 'BranchID')) {
                $blueprint->unsignedBigInteger('BranchID')->nullable();
            }
            if (! Schema::hasColumn($table, 'DepartmentID')) {
                $blueprint->unsignedBigInteger('DepartmentID')->nullable();
            }
        });

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'CreatedBy')) {
                return;
            }

            $blueprint->index('CreatedBy', 'idx_sysactivitylog_createdby');
            $blueprint->index('ModuleID', 'idx_sysactivitylog_moduleid');
            $blueprint->index('ActionType', 'idx_sysactivitylog_actiontype');
            $blueprint->index('Status', 'idx_sysactivitylog_status');
            $blueprint->index('ReferenceNo', 'idx_sysactivitylog_referenceno');
            $blueprint->index('RequestID', 'idx_sysactivitylog_requestid');
            $blueprint->index('created_at', 'idx_sysactivitylog_createdat');
        });

        DB::table($table)->whereNull('Status')->update(['Status' => 'SUCCESS']);
        DB::table($table)
            ->whereNull('ActionType')
            ->whereNotNull('event')
            ->update(['ActionType' => DB::raw('UPPER([event])')]);
        DB::table($table)
            ->whereNull('DescriptionText')
            ->whereNotNull('description')
            ->update(['DescriptionText' => DB::raw('[description]')]);
        DB::table($table)
            ->whereNull('CreatedBy')
            ->whereNotNull('causer_id')
            ->update(['CreatedBy' => DB::raw('[causer_id]')]);
        DB::table($table)
            ->whereNull('RecordID')
            ->whereNotNull('subject_id')
            ->update(['RecordID' => DB::raw('CAST([subject_id] AS NVARCHAR(100))')]);
    }

    public function down(): void
    {
        $table = config('activitylog.table_name', 't_SYSActivityLog');

        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex('idx_sysactivitylog_createdby');
                $blueprint->dropIndex('idx_sysactivitylog_moduleid');
                $blueprint->dropIndex('idx_sysactivitylog_actiontype');
                $blueprint->dropIndex('idx_sysactivitylog_status');
                $blueprint->dropIndex('idx_sysactivitylog_referenceno');
                $blueprint->dropIndex('idx_sysactivitylog_requestid');
                $blueprint->dropIndex('idx_sysactivitylog_createdat');
            });
        } catch (\Throwable) {
            // no-op
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $columns = [
                'ModuleID',
                'UserName',
                'RoleID',
                'RoleName',
                'ModuleName',
                'FeatureName',
                'ActionType',
                'EntityName',
                'TableName',
                'RecordID',
                'ReferenceNo',
                'DescriptionText',
                'OldValues',
                'NewValues',
                'ChangedFields',
                'IPAddress',
                'UserAgent',
                'DeviceName',
                'SessionID',
                'RequestID',
                'Status',
                'FailureReason',
                'CreatedBy',
                'BranchID',
                'DepartmentID',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
