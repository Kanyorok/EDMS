<?php

namespace App\Http\Controllers\DMS;

use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Models\DMS\PhysicalFileCheckout;
use App\Models\DMS\Registry;
use App\Models\DMS\StorageLocation;
use App\Services\DMS\PhysicalFileService;
use App\Services\DMS\RegistryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The return counter: files coming back from whoever borrowed them.
 *
 * The handover that opened the loan lives with the request that justified it;
 * closing one is a plain custody matter, so it answers to the checkout itself.
 */
class PhysicalFileCheckoutController extends Controller
{
    public function __construct(
        private readonly RegistryAccessService $access,
        private readonly PhysicalFileService $physicalFiles
    ) {
    }

    /**
     * Takes a lent file back and shelves it.
     *
     * A return is only finished when the paper is somewhere again, so where it
     * goes back to is part of the act rather than a later tidy-up: without it
     * the file would be "returned" and yet nowhere on the shelving.
     */
    public function checkin(Request $request, PhysicalFileCheckout $physicalFileCheckout): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        $validated = $request->validate([
            'ReturnedToLocationId' => ['required', 'integer', 'exists:t_DMSStorageLocations,Id'],
            'CheckInRemark' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $file = $physicalFileCheckout->physicalFile()->with('homeRegistry')->firstOrFail();
            $this->access->assertAccessible($request->user(), $file->homeRegistry);

            $location = StorageLocation::query()
                ->where('RegistryId', $file->HomeRegistryId)
                ->whereNull('DeletedOn')
                ->find($validated['ReturnedToLocationId']);

            if (! $location) {
                throw new ErroredException('Choose a shelf in the registry that keeps this file.');
            }

            $this->physicalFiles->checkin(
                $physicalFileCheckout,
                $location,
                $request->user(),
                $validated['CheckInRemark'] ?? null
            );

            activity()->causedBy($request->user())->performedOn($file)
                ->event('checkin')
                ->log('checked physical file ' . $file->FileNumber . ' back in to '
                    . (trim((string) $location->Path, '/') ?: $location->Code));

            return $this->succeeded('physical file checked in successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('checking in (DMS) physical file failed : ' . $e);

            return $this->errored('checking the physical file in failed, try again later');
        }
    }
}
