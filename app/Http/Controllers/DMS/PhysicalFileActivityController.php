<?php

namespace App\Http\Controllers\DMS;

use App\Exceptions\ErroredException;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\Registry;
use App\Traits\Controller\ActivitiesTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Exceptions\InvalidConfiguration;

/**
 * Everything that has been done to one paper file, in order.
 */
class PhysicalFileActivityController extends PhysicalFileTabController
{
    use ActivitiesTrait;

    public function __invoke(Request $request, PhysicalFile $physicalFile): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        try {
            $this->assertReachable($physicalFile, $request);

            return $this->activities($physicalFile->userActivities(), ['causer']);
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (InvalidConfiguration) {
            return $this->errored('invalid configuration');
        }
    }
}
