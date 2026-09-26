<?php

namespace App\Http\Controllers\DMS;

use App\Exceptions\ErroredException;
use App\Http\Requests\Base\SharePartyRequest;
use App\Models\Core\SpecialPermission;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\Registry;
use App\Traits\Controller\SpecialPermissionTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Who a paper file has been shared with, and by whose leave.
 *
 * A physical file carries the same special-permission grants a document does,
 * so the same service trait decides who may hand them out: the file's own
 * Admin/Share holders, not simply anyone who runs the registry.
 */
class PhysicalFilePermissionController extends PhysicalFileTabController
{
    use SpecialPermissionTrait;

    public function index(Request $request, PhysicalFile $physicalFile): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        try {
            $this->assertReachable($physicalFile, $request);

            return $this->permissions(
                $physicalFile->permissions(),
                $this->physicalFiles->canShare($physicalFile, $request->user())
            );
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('fetching (DMS) physical file permissions failed : ' . $e);

            return $this->errored('fetching the shares failed, try again later');
        }
    }

    public function store(SharePartyRequest $request, PhysicalFile $physicalFile): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        try {
            $this->assertReachable($physicalFile, $request);

            $this->physicalFiles->share(
                $physicalFile,
                $request->getParty(),
                $request->getRole(),
                $request->user()
            );

            return $this->succeeded('file shared successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('sharing a (DMS) physical file failed : ' . $e);

            return $this->errored('sharing the file failed, try again later');
        }
    }

    public function destroy(Request $request, PhysicalFile $physicalFile, int $permission): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        try {
            $this->assertReachable($physicalFile, $request);

            $grant = $physicalFile->permissions()->where('Id', $permission)->first();

            if (! $grant instanceof SpecialPermission) {
                return $this->errored('that share no longer exists');
            }

            $this->physicalFiles->unshare($physicalFile, $grant, $request->user());

            return $this->succeeded('share removed successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('removing a share from a (DMS) physical file failed : ' . $e);

            return $this->errored('removing the share failed, try again later');
        }
    }

    protected function _trashRoute(SpecialPermission $permission): string
    {
        return route('registry.physical-files.permissions.destroy', [
            $permission->ModelID,
            $permission->Id,
        ]);
    }
}
