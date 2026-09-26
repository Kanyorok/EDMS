<?php

namespace App\Services\DMS;

use App\Exceptions\ErroredException;
use App\Models\Auth\User;
use App\Models\Core\Branch;
use App\Models\DMS\Registry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Decides which registries the signed-in user may work in.
 *
 * Custody of physical records is a branch responsibility: a clerk in Moshi has
 * no business filing into the Dodoma strongroom. Every registry lookup in the
 * settings UI goes through here so the branch rule is applied in one place
 * rather than re-derived per controller action.
 *
 * Head office is the exception - it oversees the branch registries, so a user
 * logged into an HQ branch sees all of them.
 */
class RegistryAccessService
{
    /**
     * The branch the session is actually operating in.
     *
     * A user may be posted to one branch but log in against another, so the
     * session value wins over the profile. Matches how Finance and Procurement
     * resolve branch elsewhere in the app.
     */
    public function loginBranchId(User $user): ?int
    {
        $branchId = session('LoginBranchId') ?: $user->BranchId;

        return $branchId !== null ? (int) $branchId : null;
    }

    /**
     * Head office users oversee every branch registry.
     */
    public function isHeadOffice(User $user): bool
    {
        $branchId = $this->loginBranchId($user);

        if ($branchId === null) {
            return false;
        }

        return (bool) Branch::query()
            ->whereKey($branchId)
            ->whereNull('DeletedOn')
            ->value('IsHQ');
    }

    /**
     * Registries the user may open, before any per-registry grants apply.
     *
     * Non-HQ users are pinned to their login branch. Registries with no branch
     * are treated as head-office-only rather than shared, so an unassigned
     * registry never leaks to a branch clerk.
     *
     * @return Builder<Registry>
     */
    public function scope(User $user): Builder
    {
        $query = Registry::query()->whereNull('DeletedOn');

        if ($this->isHeadOffice($user)) {
            return $query;
        }

        $branchId = $this->loginBranchId($user);

        if ($branchId === null) {
            // No resolvable branch: show nothing rather than everything.
            return $query->whereRaw('1 = 0');
        }

        return $query->where('BranchId', $branchId);
    }

    /**
     * @return Collection<int,Registry>
     */
    public function selectable(User $user): Collection
    {
        return $this->scope($user)->orderBy('Name')->get();
    }

    /**
     * The registry the settings page opens on.
     *
     * Branch users have exactly one in practice, so the page lands on it
     * without a selection step. HQ falls back to its own registry, or the
     * first by name when head office has not been seeded a registry of its own.
     */
    public function defaultRegistry(User $user): ?Registry
    {
        $branchId = $this->loginBranchId($user);

        if ($branchId !== null) {
            $own = (clone $this->scope($user))->where('BranchId', $branchId)->orderBy('Name')->first();

            if ($own) {
                return $own;
            }
        }

        return $this->scope($user)->orderBy('Name')->first();
    }

    /**
     * Refuses anyone not logged into a head-office branch.
     *
     * Creating registries is a head-office job: a registry decides which branch
     * custodies a body of records, so letting a branch create its own would let
     * it award itself custody. Managing the shelving *inside* a registry is a
     * branch job and uses assertAccessible() instead.
     *
     * @throws ErroredException
     */
    public function assertHeadOffice(User $user): void
    {
        if (! $this->isHeadOffice($user)) {
            throw new ErroredException('Registries can only be managed from head office.');
        }
    }

    public function canAccess(User $user, Registry $registry): bool
    {
        return (clone $this->scope($user))->whereKey($registry->Id)->exists();
    }

    /**
     * Refuses a registry outside the login branch.
     *
     * Called by every controller action, not just the page load: the registry
     * arrives as a request parameter, so a branch user could otherwise reach
     * another branch's shelving by editing the id.
     *
     * @throws ErroredException
     */
    public function assertAccessible(User $user, Registry $registry): void
    {
        if (! $this->canAccess($user, $registry)) {
            throw new ErroredException('That registry belongs to another branch.');
        }
    }
}
