<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Adds BranchId (if missing) + FK constraint on t_Users -> t_Branches,
     * and drops legacy single-session columns. All operations are guarded.
     */
    public function up(): void
    {
        // 1. Ensure BranchId column exists (it may be absent if the base migration had issues)
        if (!Schema::hasColumn('t_Users', 'BranchId')) {
            Schema::table('t_Users', static function (Blueprint $table) {
                $table->unsignedBigInteger('BranchId')->nullable()->index();
            });
        }

        // 2. Add the FK constraint via raw SQL.
        //    Blueprint::foreign() also tries to create the column which would fail if it exists.
        if (Schema::hasTable('t_Branches')) {
            $constraintExists = DB::select("
                SELECT 1 FROM sys.foreign_keys
                WHERE name = 't_users_branchid_foreign'
                  AND parent_object_id = OBJECT_ID('t_Users')
            ");

            if (empty($constraintExists)) {
                DB::statement('ALTER TABLE [t_Users] ADD CONSTRAINT [t_users_branchid_foreign]
                    FOREIGN KEY ([BranchId]) REFERENCES [t_Branches] ([Id])');
            }
        }

        // 3. Drop legacy single-session columns only if they exist
        Schema::table('t_Users', static function (Blueprint $table) {
            if (Schema::hasColumn('t_Users', 'session_version')) {
                $table->dropIndex(['session_version']);
                $table->dropColumn('session_version');
            }
            if (Schema::hasColumn('t_Users', 'current_session_id')) {
                $table->dropIndex(['current_session_id']);
                $table->dropColumn('current_session_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the FK constraint if it exists
        $constraintExists = DB::select("
            SELECT 1 FROM sys.foreign_keys
            WHERE name = 't_users_branchid_foreign'
              AND parent_object_id = OBJECT_ID('t_Users')
        ");

        if (!empty($constraintExists)) {
            DB::statement('ALTER TABLE [t_Users] DROP CONSTRAINT [t_users_branchid_foreign]');
        }

        // Restore session columns if not present
        if (!Schema::hasColumn('t_Users', 'current_session_id')) {
            Schema::table('t_Users', static function (Blueprint $table) {
                $table->string('current_session_id', 255)->nullable()->index();
                $table->unsignedBigInteger('session_version')->default(1)->index();
            });
        }
    }
};
