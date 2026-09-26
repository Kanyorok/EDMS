<?php

namespace App\Http\Controllers\DMS;

use App\Enums\Core\PermissionEnum;
use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\Registry;
use App\Services\DMS\PhysicalFileService;
use App\Services\DMS\RegistryAccessService;
use Illuminate\Http\Request;

/**
 * Shared ground for the panels behind a physical file's tabs.
 *
 * Each one is fetched on its own, so each has to answer the same two questions
 * before it says anything: may this user see the file, and does the file belong
 * to a registry their branch keeps?
 */
abstract class PhysicalFileTabController extends Controller
{
    public function __construct(
        protected readonly RegistryAccessService $access,
        protected readonly PhysicalFileService $physicalFiles
    ) {
        $this->middleware('ajax');
    }

    protected function canManageFiles(User $user): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value)
            || $user->can(PermissionEnum::Admin->value);
    }

    /**
     * The file must be one this user can see, in a registry of their branch.
     *
     * @throws ErroredException
     */
    protected function assertReachable(PhysicalFile $file, Request $request): Registry
    {
        $user = $request->user();

        if (! $this->canManageFiles($user) && ! $this->physicalFiles->canSee($file, $user)) {
            throw new ErroredException('You cannot see this physical file.');
        }

        $registry = $file->homeRegistry()->firstOrFail();
        $this->access->assertAccessible($user, $registry);

        return $registry;
    }

    /**
     * Stricter still: some panels are an operator's view of the file.
     *
     * @throws ErroredException
     */
    protected function assertOperator(PhysicalFile $file, Request $request): Registry
    {
        $registry = $this->assertReachable($file, $request);
        $user = $request->user();

        if (! $this->canManageFiles($user) && ! $this->physicalFiles->canApprove($registry, $user)) {
            throw new ErroredException('Only the registry team may see that.');
        }

        return $registry;
    }
}
