<?php

namespace App\Models\Auth;

use App\Enums\Employee\GenderEnum;
use App\Models\Core\Branch;
use App\Models\HRM\Employee;
use App\Services\HRM\UserService;
use App\Traits\Controller\HasBranchRoles;
use App\Traits\Model\ImageTrait;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission;

class User extends Authenticatable
{
    use HasApiTokens;
    use ImageTrait;
    use HasFactory;
    use Notifiable;
    use UserActorTrait;
    use SoftDeletes;
    use HasBranchRoles;

    public const CREATED_AT = 'CreatedOn';
    public const UPDATED_AT = 'ModifiedOn';
    public const DELETED_AT = 'DeletedOn';

    protected $table = 't_Users';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'UserID',
        'Name',
        'Email',
        'Phone',
        'ImageId',
        'EmployeeId',
        'Notes',
        'Password',
        'Email_Signature',
        'ExtensionNo',
        'BranchId',
        'CurrentSessionId',
        'UIPreferences',
        'login_at',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'CreatedBy',
        'ModifiedBy',
        'DeletedBy',
    ];

    protected $hidden = [
        'Password',
        'remember_token',
        'Email_Signature',
        'BranchId',
        'two_factor_secret',
    ];

    protected $casts = [
        'Gender' => GenderEnum::class,
        'UIPreferences' => 'array',
        'login_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'CreatedBy' => 'integer',
    ];

    protected ?Role $effectiveRole = null;

    /**
     * Determine whether the user has fully confirmed two-factor authentication.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }

    public function isActive(): bool
    {
        return (bool)$this->IsActive;
    }

    /**
     * Internal staff accounts have no separate approval workflow (unlike ThirdPartyUser),
     * so they're considered approved as soon as they're active.
     */
    public function isApproved(): bool
    {
        return true;
    }

    public function getRoleNames(): Collection
    {
        $branchId = session('LoginBranchId');
        if (! $branchId) {
            return collect();
        }

        return ModelRole::where('model_id', $this->Id)
            ->where('model_type', 'UserID')
            ->where('BranchId', $branchId)
            ->with('role')
            ->get()
            ->pluck('role.name')
            ->filter();
    }

    public function hasRole($roles, string $guard = null): bool
    {
        $roleNames = $this->getRoleNames();

        return collect($roles)->intersect($roleNames)->isNotEmpty();
    }

    public function getPermissionsViaRoles(): Collection
    {
        $branchId = session('LoginBranchId');
        if (! $branchId) {
            return collect();
        }

        return Permission::query()
            ->whereHas('roles.modelRoles', function ($query) use ($branchId) {
                $query->where('model_id', $this->Id)
                    ->where('model_type', 'UserID')
                    ->where('BranchId', $branchId);
            })
            ->get();
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // No special bypass - admin role gets permissions like any other role
        return $this->getPermissionsViaRoles()->contains('name', $permission);
    }

    /**
     * Replace this user's role assignment(s) for a branch.
     *
     * @return array<int, string> Names of the role(s) that were replaced, for callers to
     *                            include as the "old" value in their audit log entry.
     */
    public function syncRolesWithBranch(array|Collection $roles, int $branchId, int $actorId = 1): array
    {
        $previousAssignments = ModelRole::where([
            'model_id' => $this->Id,
            'model_type' => self::getPrimaryKey(),
            'BranchId' => $branchId,
        ])->with('role')->get();

        $oldRoleNames = $previousAssignments->pluck('role.name')->filter()->values()->all();

        // Soft-delete old roles for this user + branch, preserving the prior assignment
        // row for audit reconstruction, rather than force-deleting it.
        foreach ($previousAssignments as $previousAssignment) {
            $previousAssignment->ModifiedBy = $actorId;
            $previousAssignment->DeletedBy = $actorId;
            $previousAssignment->save();
            $previousAssignment->delete();
        }

        foreach ($roles as $role) {
            // Accept any object implementing the Spatie RoleContract (both App\Models\Auth\Role
            // and Spatie\Permission\Models\Role implement it), or look up by name string.
            $roleModel = ($role instanceof \Spatie\Permission\Contracts\Role)
                ? $role
                : Role::where('name', $role)->firstOrFail();

            ModelRole::create([
                'model_id' => $this->Id,
                'model_type' => self::getPrimaryKey(),
                'BranchId' => $branchId,
                'role_id' => $roleModel->id,
                'CreatedBy' => $actorId,
                'CreatedOn' => now(),
                'ModifiedBy' => $actorId,
                'ModifiedOn' => now(),
            ]);
        }

        // This user may now be an eligible approver for workflow submissions that
        // got stuck earlier with nobody eligible to act on them (see
        // WorkflowReconciliationService). Re-check immediately rather than waiting
        // for the scheduled sweep, so approvals unblock as soon as the role lands.
        // Never let a reconciliation failure block the role assignment itself.
        if (! empty($roles)) {
            try {
                app(\App\Services\Core\WorkflowReconciliationService::class)->reconcileStuckApprovals();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Workflow reconciliation after role assignment failed', [
                    'user_id' => $this->Id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $oldRoleNames;
    }

    // Keep this as is - used for routing and morph map
    public static function getPrimaryKey(): string
    {
        return (new self())->getRouteKeyName();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'EmployeeId', 'Id');
    }

    // Keep this as is - used for routing
    public function getRouteKeyName(): string
    {
        return 'UserID';
    }

    /**
     * Get the morph class for the model.
     * This must return 'UserID' to match the morphMap and database model_type column.
     *
     * Without this override, Spatie Permission looks for model_type = 'App\Models\Auth\User'
     * but the database has model_type = 'UserID' as defined in the morphMap.
     */
    public function getMorphClass()
    {
        return 'UserID';
    }

    //  FIXED: Use Id for polymorphic lookup
    public function role(): ?Role
    {
        // Return memory-injected role if available
        if ($this->effectiveRole instanceof Role) {
            return $this->effectiveRole;
        }

        $branchId = session('LoginBranchId');
        if (! $branchId) {
            return null;
        }

        // Find branch-specific role via t_ModelRoles
        $modelRole = ModelRole::where('model_id', $this->Id)  // Changed from implicit
            ->where('model_type', self::getPrimaryKey())
            ->where('BranchId', $branchId)
            ->first();

        return  $modelRole?->role;
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 't_TeamUser', 'UserId', 'TeamId', 'Id', 'TeamID')
            ->withTimestamps('CreatedOn', 'ModifiedOn')->withPivot(['CreatedBy', 'ModifiedBy']);
    }

    public function teamUser(): HasMany
    {
        return $this->hasMany(TeamUser::class, 'UserId', "Id");
    }

    public function branchRoles(): HasMany
    {
        return $this->hasMany(ModelRole::class, 'model_id', 'Id')  // Added explicit foreign/local key
            ->where('model_type', self::getPrimaryKey())
            ->with(['role', 'branch']);
    }

    public function getEmailForPasswordReset()
    {
        return $this->Email;
    }

    public function sendPasswordResetNotification($token = ''): void
    {
        (new UserService($this))->sendPasswordResetNotification();
    }

    /**
     * Scopes a query to include models that have specific permissions, per current login branch.
     *
     * @param Builder $query The query builder instance.
     * @param string|array $permissions The permission(s) to filter the query by. Can be a string of comma-separated values or an array of permission names.
     * @return Builder The modified query builder instance.
     */
    public function scopeHasPermission(Builder $query, string|array $permissions): Builder
    {
        if (is_string($permissions)) {
            $permissions = array_map('trim', explode(',', $permissions));
        }

        // Uses the custom branchRoles relation (t_ModelRoles) instead of Spatie native relations,
        // since this project manages roles via ModelRole rather than Spatie's model_has_roles table.
        return $query->whereHas('branchRoles', function (Builder $q) use ($permissions) {
            $q->whereHas('role.permissions', function (Builder $q2) use ($permissions) {
                $q2->whereIn('name', $permissions);
            });
        });
    }

    public function scopeHasBranchPermissionRole(Builder $query, string|array $permissions, string|array $branches): Builder
    {
        if (is_string($permissions)) {
            $permissions = array_map('trim', explode(',', $permissions));
        }
        if (is_string($branches)) {
            $branches = array_map('trim', explode(',', $branches));
        }


        return $query->whereHas('branchRoles', function (Builder $query) use ($permissions, $branches) {
            $query->where(function (Builder $q) use ($branches) {
                $q->whereIn('t_ModelRoles.BranchId', $branches);
            })->whereHas('role.permissions', function (Builder $q) use ($permissions) {
                $q->whereIn('t_Permissions.name', $permissions);
            });
        });
    }

    protected function getImageName(): string
    {
        return $this->Name;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'Id');
    }
}
