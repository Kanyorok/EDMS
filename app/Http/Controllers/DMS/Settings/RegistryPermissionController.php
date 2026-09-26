<?php

namespace App\Http\Controllers\DMS\Settings;

use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Base\SharePartyRequest;
use App\Models\Core\SpecialPermission;
use App\Models\DMS\Registry;
use App\Services\DMS\RegistryAccessService;
use App\Services\DMS\RegistryService;
use App\Traits\Controller\SpecialPermissionTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistryPermissionController extends Controller
{
    use SpecialPermissionTrait;

    public function __construct(
        private readonly RegistryAccessService $access,
        private readonly RegistryService $registries
    ) {
        $this->middleware('ajax');
    }

    public function index(Request $request, Registry $registry): JsonResponse
    {
        $this->authorize('view', $registry);

        try {
            $this->assertMayManage($registry, $request);

            return $this->permissions($registry->permissions(), true);
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('fetching (DMS) registry permissions failed : ' . $e);

            return $this->errored('fetching the shares failed, try again later');
        }
    }

    public function store(SharePartyRequest $request, Registry $registry): JsonResponse
    {
        $this->authorize('view', $registry);

        try {
            $this->assertMayManage($registry, $request);

            $this->registries->share(
                $registry,
                $request->getParty(),
                $request->getRole(),
                $request->user()
            );

            return $this->succeeded('registry shared successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('sharing a (DMS) registry failed : ' . $e);

            return $this->errored('sharing the registry failed, try again later');
        }
    }

    public function destroy(Request $request, Registry $registry, int $permission): JsonResponse
    {
        $this->authorize('view', $registry);

        try {
            $this->assertMayManage($registry, $request);

            $grant = $registry->permissions()->where('Id', $permission)->first();

            if (! $grant instanceof SpecialPermission) {
                return $this->errored('that permission no longer exists');
            }

            $this->registries->unshare($registry, $grant, $request->user());

            return $this->succeeded('permission removed successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('removing a share from a (DMS) registry failed : ' . $e);

            return $this->errored('removing the share failed, try again later');
        }
    }

    protected function _trashRoute(SpecialPermission $permission): string
    {
        return route('registries.permissions.destroy', [$permission->ModelID, $permission->Id]);
    }

    /**
     * The registry must be reachable from this branch, and the user must hold
     * the right to manage its permissions.
     *
     * @throws ErroredException
     */
    private function assertMayManage(Registry $registry, Request $request): void
    {
        $this->access->assertAccessible($request->user(), $registry);

        if (! $this->registries->canManagePermissions($registry, $request->user())) {
            throw new ErroredException('You may not manage the permissions of this registry.');
        }
    }
}
