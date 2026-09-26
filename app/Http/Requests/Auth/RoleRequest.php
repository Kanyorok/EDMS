<?php

namespace App\Http\Requests\Auth;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\ModulesEnum;
use App\Models\Auth\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'RoleName' => [
                'required',
                'string',
                'max:200',
            ],
            'permissions_payload' => [
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function getName(Role $role = null): string
    {
        $name = $this->validated('RoleName');
        $check_role = Role::query();
        if ($role instanceof Role) {
            $check_role->where('id', '!=', $role->id);
        }
        if ($check_role->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['RoleName' => "Role name already exists."]);
        }

        return $name;
    }

    /**
     * @throws ValidationException
     */
    public function getPermissions(): array
    {
        $payload = $this->input('permissions_payload');
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw ValidationException::withMessages(['permissions' => 'Invalid permissions payload.']);
            }

            $names = collect($decoded)
                ->filter(static fn ($name): bool => is_string($name) && trim($name) !== '')
                ->map(static fn (string $name): string => trim($name))
                ->filter(static function (string $name): bool {
                    $enum = PermissionEnum::tryFrom($name);

                    return ! $enum instanceof PermissionEnum
                        || ($enum->isAssignableInRolesScreen() && $enum->isAssignableToRoles());
                })
                ->unique()
                ->values();

            if ($names->isEmpty()) {
                throw ValidationException::withMessages(['permissions' => 'select at least one permission']);
            }

            $moduleIds = array_map(static fn (ModulesEnum $m): int => $m->value, PermissionEnum::rolesScreenModules());
            $guard = Guard::getDefaultName(User::class);
            $permissionTable = config('permission.table_names.permissions');
            $now = now();

            $existingNames = Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('ModuleId', $moduleIds)
                ->whereIn('name', $names->all())
                ->pluck('name')
                ->all();

            $missingNames = $names->diff($existingNames);
            if ($missingNames->isNotEmpty()) {
                $rows = [];
                foreach ($missingNames as $name) {
                    $enum = PermissionEnum::tryFrom($name);
                    if (! $enum instanceof PermissionEnum) {
                        continue;
                    }
                    if (! $enum->isAssignableInRolesScreen()) {
                        continue;
                    }

                    $rows[] = [
                        'name' => $enum->value,
                        'ModuleId' => $enum->module()->value,
                        'guard_name' => $guard,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($rows)) {
                    DB::table($permissionTable)->upsert(
                        $rows,
                        ['name', 'guard_name'],
                        ['ModuleId', 'updated_at']
                    );
                }
            }

            $selectedIds = Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('ModuleId', $moduleIds)
                ->whereIn('name', $names->all())
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if (empty($selectedIds)) {
                throw ValidationException::withMessages(['permissions' => 'select at least one permission']);
            }

            return $selectedIds;
        }

        $moduleIds = array_map(static fn (ModulesEnum $m): int => $m->value, PermissionEnum::rolesScreenModules());
        $allPermissions = Permission::query()
            ->whereIn('ModuleId', $moduleIds)
            ->get();
        $selectedIds = [];

        foreach ($allPermissions as $permission) {
            $enum = PermissionEnum::tryFrom($permission->name);
            if ($enum instanceof PermissionEnum && ! $enum->isAssignableToRoles()) {
                continue;
            }

            // Normalize DB permission name and request keys to lowercase for comparison
            $dbName = strtolower($permission->name);
            $dbKey = str_replace([' ', '.'], '_', $dbName);

            // Check if any input key matches the normalized DB key
            $inputKeys = collect($this->all())->keys()->map(fn ($k) => str_replace([' ', '.'], '_', strtolower($k)));

            // Direct check (optimization)
            if ($this->has($permission->name) || $this->has(str_replace([' ', '.'], '_', $permission->name))) {
                $selectedIds[] = $permission->id;

                continue;
            }

            // Case-insensitive fallback
            foreach ($this->all() as $key => $value) {
                if ($value !== 'on') {
                    continue;
                }
                $normalizedKey = str_replace([' ', '.'], '_', strtolower($key));
                if ($normalizedKey === $dbKey) {
                    $selectedIds[] = $permission->id;

                    break;
                }
            }
        }

        if (empty($selectedIds)) {
            throw ValidationException::withMessages(['permissions' => 'select at least one permission']);
        }

        return $selectedIds;
    }
}
