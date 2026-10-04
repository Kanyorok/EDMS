<?php

namespace Database\Seeders;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\ModulesEnum;
use App\Helpers\SystemHelper;
use App\Models\Auth\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Sync enum-managed permissions while protecting workflow and report permissions.
     */
    public function run(): void
    {
        $actor = SystemHelper::user();
        $now = now();
        $guard = Guard::getDefaultName(User::class);
        $table = config('permission.table_names.permissions');

        $this->ensureBaselineRoles($guard, $now, $actor);

        $adminRole = Role::query()->createOrFirst(
            ['name' => 'admin', 'guard_name' => $guard],
            ['CreatedBy' => $actor->Id ?? 1, 'ModifiedBy' => $actor->Id ?? 1]
        );

        $validPermissions = collect(PermissionEnum::cases())
            ->filter(static fn (PermissionEnum $permission): bool => self::isStandalonePermission($permission))
            ->map(static fn (PermissionEnum $permission): string => $permission->value)
            ->values();

        $obsoletePermissionIds = DB::table($table)
            ->where('guard_name', $guard)
            ->whereNotIn('name', $validPermissions->all())
            ->pluck('id');

        $deleted = DB::transaction(function () use ($table, $obsoletePermissionIds): int {
            if ($obsoletePermissionIds->isEmpty()) {
                return 0;
            }

            $permissionKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';
            DB::table(config('permission.table_names.role_has_permissions'))
                ->whereIn($permissionKey, $obsoletePermissionIds)
                ->delete();
            DB::table(config('permission.table_names.model_has_permissions'))
                ->whereIn($permissionKey, $obsoletePermissionIds)
                ->delete();

            return DB::table($table)->whereIn('id', $obsoletePermissionIds)->delete();
        });

        if ($deleted > 0) {
            echo "  Removed {$deleted} non-DMS permissions." . PHP_EOL;
        }

        $rows = collect(PermissionEnum::cases())
            ->filter(static fn (PermissionEnum $permission): bool => self::isStandalonePermission($permission))
            ->map(static function (PermissionEnum $permission) use ($guard, $now): array {
                return [
                    'name' => $permission->value,
                    'ModuleId' => $permission->module()->value,
                    'guard_name' => $guard,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();

        if (! empty($rows)) {
            DB::connection()->disableQueryLog();
            DB::transaction(function () use ($table, $rows): void {
                foreach (array_chunk($rows, 400) as $chunk) {
                    DB::table($table)->upsert(
                        $chunk,
                        ['name', 'guard_name'],
                        ['ModuleId', 'updated_at']
                    );
                }
            });
        }

        $permissionIds = DB::table($table)
            ->where('guard_name', $guard)
            ->pluck('id')
            ->all();

        echo 'Assigning ' . count($permissionIds) . ' permissions to admin role.' . PHP_EOL;

        $pivotValues = [
            'CreatedBy' => $actor->Id ?? 1,
            'ModifiedBy' => $actor->Id ?? 1,
        ];

        foreach (array_chunk($permissionIds, 500) as $permissionChunk) {
            $attachPayload = [];
            foreach ($permissionChunk as $permissionId) {
                $attachPayload[$permissionId] = $pivotValues;
            }

            $adminRole->permissions()->syncWithoutDetaching($attachPayload);
        }

        User::query()
            ->whereDoesntHave('roles')
            ->orderBy('Id')
            ->chunkById(500, function ($users) use ($adminRole): void {
                $now = now();

                foreach ($users as $user) {
                    $user->setRelation('roles', null);
                    $user->roles()->syncWithoutDetaching([
                        $adminRole->id => [
                            'BranchId' => $user->BranchId ?? 1,
                            'CreatedOn' => $now,
                            'ModifiedOn' => $now,
                        ],
                    ]);
                }
            });
    }

    /**
     * Create the baseline system roles if they do not already exist.
     */
    private function ensureBaselineRoles(string $guard, $now, object $actor): void
    {
        if (! Role::query()->where('name', 'Default')->exists()) {
            Role::create([
                'name' => 'Default',
                'guard_name' => $guard,
                'created_at' => $now,
                'updated_at' => $now,
                'CreatedBy' => $actor->Id ?? 1,
                'ModifiedBy' => $actor->Id ?? 1,
            ]);
        }
    }

    /**
     * Permissions required by the standalone DMS and its retained administration surface.
     */
    private static function isStandalonePermission(PermissionEnum $permission): bool
    {
        if (! $permission->isAssignableToRoles()) {
            return false;
        }

        if ($permission->module() === ModulesEnum::DMS) {
            return true;
        }

        return in_array($permission, [
            PermissionEnum::Admin,
            PermissionEnum::Users,
            PermissionEnum::UsersSessions,
            PermissionEnum::Roles,
            PermissionEnum::Teams,
            PermissionEnum::ListsView,
            PermissionEnum::ListsUpdate,
            PermissionEnum::Integrations,
            PermissionEnum::OrganizationProfile,
            PermissionEnum::SetBaseCurrency,
            PermissionEnum::BranchView,
            PermissionEnum::BranchCreate,
            PermissionEnum::BranchUpdate,
            PermissionEnum::BranchDelete,
            PermissionEnum::BranchSyncAct,
            PermissionEnum::WorkflowView,
            PermissionEnum::WorkflowCreate,
            PermissionEnum::WorkflowUpdate,
            PermissionEnum::WorkflowDelete,
            PermissionEnum::EmployeesView,
            PermissionEnum::EmployeesCreate,
            PermissionEnum::EmployeesUpdate,
            PermissionEnum::EmployeesDelete,
            PermissionEnum::Departments,
        ], true);
    }

}
