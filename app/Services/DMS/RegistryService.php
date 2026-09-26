<?php

namespace App\Services\DMS;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\RoleEnum;
use App\Enums\Core\VisibilityEnum;
use App\Exceptions\ErroredException;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Models\Core\SpecialPermission;
use App\Models\DMS\Registry;
use App\Models\DMS\StorageLocation;
use App\Traits\Services\SpecialPermissionsTrait;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Registry records themselves - the departments that custody paper.
 *
 * The shelving inside a registry is StorageLocationService's job. The two meet
 * in one place: a registry Code is the root segment of every Path beneath it,
 * so renaming a registry has to rewrite that whole subtree.
 */
class RegistryService
{
    use SpecialPermissionsTrait;

    /** Same charset as a location code - this becomes the root Path segment. */
    private const CODE_PATTERN = '/^[A-Za-z0-9._-]{1,20}$/';

    public function __construct(private readonly StorageLocationService $locations)
    {
    }

    /**
     * @param array<string,mixed> $attributes
     * @throws ErroredException|Throwable
     */
    public function create(array $attributes, User $actor): Registry
    {
        // The form no longer collects a code - it is generated here so a
        // registry's identity doesn't depend on someone picking one by hand.
        $code = trim((string) ($attributes['Code'] ?? ''));
        if ($code === '') {
            $code = $this->generateCode();
        }
        $this->assertValidCode($code);
        $this->assertCodeAvailable($code);

        return DB::transaction(function () use ($attributes, $actor, $code) {
            $registry = new Registry($attributes);

            $registry->Code = $code;
            $registry->CreatedBy = $actor->Id;
            $registry->ModifiedBy = $actor->Id;
            $registry->Visibility = $attributes['Visibility'] ?? VisibilityEnum::Public->value;
            // Statuses aren't user-chosen yet - every registry starts "active"
            // until the approval workflow (and its own statuses) replaces this.
            $registry->RegistryStatusId = $attributes['RegistryStatusId'] ?? $this->defaultStatusId();

            $registry->save();

            $this->grantHeadOfRegistry($registry, $actor);

            return $registry;
        });
    }

    /**
     * Next free "REG-0001"-style code. Sequence starts from the highest Id
     * rather than a row count so a deleted registry never causes a collision.
     */
    public function generateCode(): string
    {
        $sequence = (int) (Registry::query()->withTrashed()->max('Id') ?? 0) + 1;

        do {
            $code = 'REG-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (! $this->isCodeAvailable($code));

        return $code;
    }

    /**
     * The one status a registry can have until the approval workflow adds
     * the rest. Mirrors DmsRegistrySeeder::activeStatusId().
     *
     * @throws ErroredException
     */
    private function defaultStatusId(): int
    {
        $statusId = DB::table('t_CodeDetails')
            ->where('CodeID', 'RegistryStatusId')
            ->where('Value', 'active')
            ->whereNull('DeletedOn')
            ->value('ID');

        if ($statusId === null) {
            throw new ErroredException('Registry status "active" is not configured.');
        }

        return (int) $statusId;
    }

    /**
     * The head of a registry gets Admin over it.
     *
     * Naming somebody head of a registry is the act of putting them in charge,
     * so the grant that says so is written at the same time - it is what lets
     * them approve requests and hand out access in turn. Idempotent, so saving
     * a registry also heals a head who was named before this was so.
     */
    private function grantHeadOfRegistry(Registry $registry, User $actor): void
    {
        if (! $registry->HodUserId) {
            return;
        }

        $registry->permissions()->updateOrCreate(
            [
                'Party' => User::getPrimaryKey(),
                'PartyID' => $registry->HodUserId,
            ],
            [
                'Permission' => RoleEnum::Admin->value,
                'CreatedBy' => $actor->Id,
                'ModifiedBy' => $actor->Id,
            ]
        );
    }

    /**
     * Who may read and hand out a registry's permissions.
     *
     * Either the global right to manage permissions, or an Admin grant on this
     * registry - which is not a formality: PhysicalFileService::canApprove()
     * reads the same grant to decide who may approve its file requests.
     */
    public function canManagePermissions(Registry $registry, User $actor): bool
    {
        if ($actor->can(PermissionEnum::Roles->value) || $actor->can(PermissionEnum::Admin->value)) {
            return true;
        }

        return Registry::query()
            ->userGrant($actor, [RoleEnum::Admin->value])
            ->whereKey($registry->Id)
            ->exists();
    }

    /**
     * @throws ErroredException
     */
    public function share(
        Registry $registry,
        User|Team $assignee,
        RoleEnum $role,
        User $actor,
        bool $notify = true
    ): SpecialPermission {
        return $this->_addPermissions($registry, $assignee, $role, $actor, $notify);
    }

    /**
     * @throws ErroredException
     */
    public function unshare(Registry $registry, SpecialPermission $permission, User $actor): Registry
    {
        return $this->_trashPermissions($registry, $permission, $actor);
    }

    /**
     * @param array<string,mixed> $attributes
     * @throws ErroredException|Throwable
     */
    public function update(Registry $registry, array $attributes, User $actor): Registry
    {
        $code = (string) ($attributes['Code'] ?? '');
        $this->assertValidCode($code);
        $this->assertCodeAvailable($code, $registry->Id);
        $this->assertNoParentCycle($registry, $attributes['ParentRegistryId'] ?? null);

        return DB::transaction(function () use ($registry, $attributes, $actor, $code) {
            // Delegated, not assigned: every Path under this registry starts
            // with its code and has to be rewritten with it.
            if ($code !== $registry->Code) {
                $this->locations->renameRegistry($registry, $code, $actor);
            }

            $registry->refresh()->update([
                'Name' => $attributes['Name'],
                'Description' => $attributes['Description'] ?? null,
                'ParentRegistryId' => $attributes['ParentRegistryId'] ?? null,
                'BranchId' => $attributes['BranchId'] ?? null,
                'HodUserId' => $attributes['HodUserId'],
                'IsArchive' => (bool) ($attributes['IsArchive'] ?? false),
                // Not user-editable yet - see create(). Keeps whatever the registry already has.
                'RegistryStatusId' => $attributes['RegistryStatusId'] ?? $registry->RegistryStatusId,
                'Visibility' => $attributes['Visibility'] ?? VisibilityEnum::Public->value,
                'ModifiedBy' => $actor->Id,
            ]);

            $this->grantHeadOfRegistry($registry->refresh(), $actor);

            return $registry->refresh();
        });
    }

    /**
     * Soft-deletes a registry, refusing while anything still depends on it.
     *
     * Shelving would otherwise be left pointing at a dead registry with a Path
     * naming a code that no longer resolves, and child registries would be
     * orphaned the same way.
     *
     * @throws ErroredException|Throwable
     */
    public function delete(Registry $registry, User $actor): void
    {
        $locations = StorageLocation::query()
            ->where('RegistryId', $registry->Id)
            ->whereNull('DeletedOn')
            ->count();

        if ($locations > 0) {
            throw new ErroredException(
                "Cannot delete this registry: it still holds {$locations} storage location(s)."
            );
        }

        $children = Registry::query()
            ->where('ParentRegistryId', $registry->Id)
            ->whereNull('DeletedOn')
            ->count();

        if ($children > 0) {
            throw new ErroredException(
                "Cannot delete this registry: {$children} other registry(ies) report to it."
            );
        }

        DB::transaction(function () use ($registry, $actor) {
            $registry->DeletedBy = $actor->Id;
            $registry->save();
            $registry->delete();
        });
    }

    /**
     * Registries nest, so the same cycle risk as the location tree applies.
     * The depth is small, so an upward walk is cheaper than a materialized path.
     *
     * @throws ErroredException
     */
    public function assertNoParentCycle(Registry $registry, mixed $parentId): void
    {
        $parentId = $parentId !== null ? (int) $parentId : null;

        if ($parentId === null) {
            return;
        }

        if ($parentId === (int) $registry->Id) {
            throw new ErroredException('A registry cannot report to itself.');
        }

        $seen = [(int) $registry->Id];
        $cursor = $parentId;

        while ($cursor !== null) {
            if (in_array($cursor, $seen, true)) {
                throw new ErroredException('That parent would create a loop in the registry tree.');
            }

            $seen[] = $cursor;
            $next = Registry::query()->whereKey($cursor)->value('ParentRegistryId');
            $cursor = $next !== null ? (int) $next : null;
        }
    }

    /**
     * @throws ErroredException
     */
    private function assertValidCode(string $code): void
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new ErroredException(
                'A registry code may only contain letters, digits, dot, dash or underscore (max 20).'
            );
        }
    }

    /**
     * The database has a filtered unique index on Code; this turns the eventual
     * constraint violation into something a user can act on.
     *
     * @throws ErroredException
     */
    private function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        if (! $this->isCodeAvailable($code, $ignoreId)) {
            throw new ErroredException("Registry code '{$code}' is already in use.");
        }
    }

    private function isCodeAvailable(string $code, ?int $ignoreId = null): bool
    {
        return ! Registry::query()
            ->where('Code', $code)
            ->whereNull('DeletedOn')
            ->when($ignoreId !== null, fn ($q) => $q->where('Id', '<>', $ignoreId))
            ->exists();
    }
}
