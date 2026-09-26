<?php

namespace App\Http\Controllers\DMS;

use App\Exceptions\ErroredException;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\PhysicalFileMovement;
use App\Models\DMS\Registry;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yajra\DataTables\DataTables;

/**
 * Where one paper file has been, in the order it went.
 */
class PhysicalFileMovementController extends PhysicalFileTabController
{
    public function __invoke(Request $request, PhysicalFile $physicalFile): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        try {
            $this->assertOperator($physicalFile, $request);

            $query = $physicalFile->movements()
                ->lock('WITH(NOLOCK)')
                ->with([
                    'movementType', 'fromRegistry', 'fromLocation', 'fromHolder',
                    'toRegistry', 'toLocation', 'toHolder', 'dispatchedBy', 'receivedBy',
                ]);

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('Movement', fn (PhysicalFileMovement $movement) => $this->reference($movement))
                ->addColumn('Route', fn (PhysicalFileMovement $movement) => $this->route($movement))
                ->addColumn('Dispatched', fn (PhysicalFileMovement $movement) => $this->handover(
                    $movement->DispatchedOn,
                    $movement->dispatchedBy?->Name,
                    '—'
                ))
                ->addColumn('Received', fn (PhysicalFileMovement $movement) => $this->handover(
                    $movement->ReceivedOn,
                    $movement->receivedBy?->Name,
                    'not yet'
                ))
                ->rawColumns(['Movement', 'Route', 'Dispatched', 'Received'])
                ->make();
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('fetching (DMS) physical file movements failed : ' . $e);

            return $this->errored('fetching the custody history failed, try again later');
        }
    }

    private function reference(PhysicalFileMovement $movement): string
    {
        return '<span class="text-nowrap">MOV-' . str_pad((string) $movement->Id, 6, '0', STR_PAD_LEFT)
            . '</span><br><small class="text-muted">'
            . e($movement->movementType?->Description ?: '-') . '</small>';
    }

    private function route(PhysicalFileMovement $movement): string
    {
        $from = $this->party(
            $movement->fromHolder?->Name,
            $movement->fromLocation?->Label,
            $movement->fromRegistry?->Name
        );
        $to = $this->party(
            $movement->toHolder?->Name,
            $movement->toLocation?->Label,
            $movement->toRegistry?->Name
        );

        return e($from) . ' &rarr; ' . e($to);
    }

    /**
     * One end of a hand-over: when it happened and who did it.
     */
    private function handover(?CarbonInterface $on, ?string $by, string $fallback): string
    {
        return ($on?->format('d M Y') ?: $fallback)
            . '<br><small class="text-muted">' . e($by ?: '-') . '</small>';
    }

    /**
     * A movement names a person, a shelf or a whole registry - whichever end of
     * it was recorded.
     */
    private function party(?string $holder, ?string $location, ?string $registry): string
    {
        return $holder ?: ($location ?: ($registry ?: '—'));
    }
}
