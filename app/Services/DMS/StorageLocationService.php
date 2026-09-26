<?php

namespace App\Services\DMS;

use App\Exceptions\ErroredException;
use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Models\DMS\LocationTypeRule;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\Registry;
use App\Models\DMS\StorageLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Owns every invariant of the shelving tree that the database cannot express.
 *
 * The schema guarantees a parent exists, that it belongs to the same registry,
 * and that a node is not its own parent. Everything else lives here: legal
 * nesting, longer cycles, and the materialized Path / Depth pair.
 */
class StorageLocationService
{
    /**
     * Path segments become part of a LIKE prefix, so the code charset is
     * restricted to keep LIKE metacharacters ('%', '_', '[') out of it.
     */
    private const string CODE_PATTERN = '/^[A-Za-z0-9._-]{1,30}$/';

    private const int CODE_CACHE_TTL = 3600;

    /**
     * Resolves a t_CodeDetails row to its identity value.
     *
     * Identity values differ between environments, so nothing may hard-code
     * them. Cached per bucket because these are read on every tree write.
     *
     * @throws ErroredException
     */
    public function codeId(string $bucket, string $value): int
    {
        $map = Cache::remember(
            'dms.codes.' . $bucket,
            self::CODE_CACHE_TTL,
            static fn () => CodeDetail::query()
                ->where('CodeID', $bucket)
                ->whereNull('DeletedOn')
                ->pluck('ID', 'Value')
                ->all()
        );

        if (! isset($map[$value])) {
            throw new ErroredException("Unknown {$bucket} value '{$value}'. Has CodeDetailSeeder been run?");
        }

        return (int) $map[$value];
    }

    public function forgetCodeCache(string $bucket): void
    {
        Cache::forget('dms.codes.' . $bucket);
    }

    /**
     * Adds a node under $parent, or directly in $registry when $parent is null.
     *
     * @param array<string,mixed> $attributes
     * @throws ErroredException|Throwable
     */
    public function create(
        Registry $registry,
        ?StorageLocation $parent,
        array $attributes,
        User $actor
    ): StorageLocation {
        $code = (string) ($attributes['Code'] ?? '');
        $this->assertValidCode($code);

        if ($parent && (int) $parent->RegistryId !== (int) $registry->Id) {
            throw new ErroredException('The parent location belongs to a different registry.');
        }

        $typeId = (int) $attributes['LocationTypeId'];
        $this->assertNestingAllowed($parent?->LocationTypeId, $typeId);

        return DB::transaction(function () use ($registry, $parent, $attributes, $actor, $code, $typeId) {
            $location = new StorageLocation($attributes);

            $location->RegistryId = $registry->Id;
            $location->ParentId = $parent?->Id;
            $location->LocationTypeId = $typeId;
            $location->Code = $code;
            $location->Depth = $parent ? $parent->Depth + 1 : 0;
            $location->CreatedBy = $actor->Id;
            $location->ModifiedBy = $actor->Id;
            $location->Path = $this->buildPath($registry, $parent, $code);

            $location->save();

            return $location;
        });
    }

    /**
     * Re-parents a node and rewrites the Path of everything beneath it.
     *
     * @throws ErroredException|Throwable
     */
    public function move(StorageLocation $node, ?StorageLocation $newParent, User $actor): StorageLocation
    {
        if ($newParent && (int) $newParent->RegistryId !== (int) $node->RegistryId) {
            throw new ErroredException('A location cannot be moved into a different registry.');
        }

        $this->assertNoCycle($node, $newParent);
        $this->assertNestingAllowed($newParent?->LocationTypeId, (int) $node->LocationTypeId);

        return DB::transaction(function () use ($node, $newParent, $actor) {
            $registry = Registry::query()->findOrFail($node->RegistryId);
            $oldPath = $node->Path;
            $newPath = $this->buildPath($registry, $newParent, $node->Code);
            $newDepth = $newParent ? $newParent->Depth + 1 : 0;

            $node->ParentId = $newParent?->Id;
            $node->ModifiedBy = $actor->Id;
            $node->save();

            $this->rewriteSubtree($node, $oldPath, $newPath, $newDepth - $node->Depth);

            return $node->refresh();
        });
    }

    /**
     * Changes a node's own code, rewriting its subtree's Path prefix.
     *
     * @throws ErroredException|Throwable
     */
    public function rename(StorageLocation $node, string $code, User $actor): StorageLocation
    {
        $this->assertValidCode($code);

        return DB::transaction(function () use ($node, $code, $actor) {
            $registry = Registry::query()->findOrFail($node->RegistryId);
            $parent = $node->ParentId ? StorageLocation::query()->find($node->ParentId) : null;

            $oldPath = $node->Path;
            $newPath = $this->buildPath($registry, $parent, $code);

            $node->Code = $code;
            $node->ModifiedBy = $actor->Id;
            $node->save();

            $this->rewriteSubtree($node, $oldPath, $newPath, 0);

            return $node->refresh();
        });
    }

    /**
     * A registry code is the root segment of every Path inside it, so changing
     * it invalidates the whole registry unless the paths are rebuilt.
     *
     * @throws ErroredException|Throwable
     */
    public function renameRegistry(Registry $registry, string $code, User $actor): Registry
    {
        $this->assertValidCode($code);

        return DB::transaction(function () use ($registry, $code, $actor) {
            $oldPrefix = '/' . $registry->Code . '/';
            $newPrefix = '/' . $code . '/';

            $registry->Code = $code;
            $registry->ModifiedBy = $actor->Id;
            $registry->save();

            DB::statement(
                'UPDATE t_DMSStorageLocations
                 SET Path = ? + SUBSTRING(Path, ?, 400),
                     ModifiedBy = ?,
                     ModifiedOn = ?
                 WHERE RegistryId = ? AND DeletedOn IS NULL AND Path LIKE ?',
                [
                    $newPrefix,
                    strlen($oldPrefix) + 1,
                    $actor->Id,
                    now(),
                    $registry->Id,
                    $oldPrefix . '%',
                ]
            );

            return $registry->refresh();
        });
    }

    /**
     * Soft-deletes a node. Refuses while it still has live children, otherwise
     * they would survive pointing at a dead parent, with a Path naming an
     * ancestor that no longer exists.
     *
     * @throws ErroredException|Throwable
     */
    public function delete(StorageLocation $node, User $actor): void
    {
        $liveChildren = $node->children()->whereNull('DeletedOn')->count();

        if ($liveChildren > 0) {
            throw new ErroredException(
                "Cannot delete this location: it still holds {$liveChildren} nested location(s)."
            );
        }

        $files = PhysicalFile::query()
            ->where(function ($query) use ($node) {
                $query->where('HomeLocationId', $node->Id)
                    ->orWhere('CurrentLocationId', $node->Id);
            })
            ->whereNull('DeletedOn')
            ->count();

        if ($files > 0) {
            throw new ErroredException(
                "Cannot delete this location: it is the home or current place of {$files} physical file(s)."
            );
        }

        DB::transaction(function () use ($node, $actor) {
            $node->DeletedBy = $actor->Id;
            $node->save();
            $node->delete();
        });
    }

    /**
     * Rejects a move that would place a node inside its own subtree.
     *
     * The database blocks only the one-hop case (ParentId <> Id); A -> B -> A
     * needs the Path prefix test. Without this, path maintenance would recurse
     * until it exhausted memory.
     *
     * @throws ErroredException
     */
    public function assertNoCycle(StorageLocation $node, ?StorageLocation $newParent): void
    {
        if (! $newParent) {
            return;
        }

        if ((int) $newParent->Id === (int) $node->Id) {
            throw new ErroredException('A location cannot be placed inside itself.');
        }

        if ($node->Path && $newParent->Path && str_starts_with($newParent->Path, $node->Path)) {
            throw new ErroredException('A location cannot be placed inside one of its own descendants.');
        }
    }

    /**
     * Checks the arrangement against t_DMSLocationTypeRules. A null parent type
     * means the child is being placed directly in the registry.
     *
     * @throws ErroredException
     */
    public function assertNestingAllowed(?int $parentTypeId, int $childTypeId): void
    {
        $allowed = LocationTypeRule::query()
            ->where('ChildTypeId', $childTypeId)
            ->where('IsActive', true)
            ->whereNull('DeletedOn')
            ->when(
                $parentTypeId === null,
                static fn ($q) => $q->whereNull('ParentTypeId'),
                static fn ($q) => $q->where('ParentTypeId', $parentTypeId)
            )
            ->exists();

        if (! $allowed) {
            throw new ErroredException('That location type may not be nested there.');
        }
    }

    /**
     * Paths carry a leading and trailing slash so that a descendant test is an
     * unambiguous prefix match: '/HR-REG/CAB-04/' cannot match '/HR-REG/CAB-041/'.
     */
    public function buildPath(Registry $registry, ?StorageLocation $parent, string $code): string
    {
        $prefix = $parent !== null ? $parent->Path : '/' . $registry->Code . '/';

        return $prefix . $code . '/';
    }

    /**
     * Swaps one Path prefix for another across a node and all its descendants,
     * in a single statement rather than a walk.
     *
     * SQL Server's LEN() ignores trailing spaces, so the offset is computed in
     * PHP and bound as an integer instead.
     */
    private function rewriteSubtree(
        StorageLocation $node,
        string $oldPath,
        string $newPath,
        int $depthDelta
    ): void {
        DB::statement(
            'UPDATE t_DMSStorageLocations
             SET Path = ? + SUBSTRING(Path, ?, 400),
                 Depth = Depth + ?
             WHERE RegistryId = ? AND DeletedOn IS NULL AND Path LIKE ?',
            [
                $newPath,
                strlen($oldPath) + 1,
                $depthDelta,
                $node->RegistryId,
                $oldPath . '%',
            ]
        );
    }

    /**
     * @throws ErroredException
     */
    private function assertValidCode(string $code): void
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new ErroredException(
                'A location code may only contain letters, digits, dot, dash or underscore (max 30).'
            );
        }
    }
}
