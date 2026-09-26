<?php

use App\Enums\Core\PermissionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_Modules') || ! Schema::hasColumn('t_Modules', 'RequiredPermission')) {
            return;
        }

        DB::table('t_Modules')
            ->where('ModuleID', 9800800)
            ->update([
                'RequiredPermission' => PermissionEnum::Admin->value . '|' . PermissionEnum::Roles->value,
                'ModifiedOn' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_Modules') || ! Schema::hasColumn('t_Modules', 'RequiredPermission')) {
            return;
        }

        DB::table('t_Modules')
            ->where('ModuleID', 9800800)
            ->update([
                'RequiredPermission' => PermissionEnum::Admin->value,
                'ModifiedOn' => now(),
            ]);
    }
};
