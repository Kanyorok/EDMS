<?php

namespace App\Http\Controllers\Settings\Users;

use App\Http\Controllers\Controller;
use App\Models\Audit\ActivityLog;
use App\Models\Auth\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class UserActivitiesController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax');
    }

    /**
     * Handle the incoming request.
     * @throws Exception
     */
    public function __invoke(Request $request, User $user): JsonResponse
    {
        return Datatables::of(ActivityLog::causedBy($user)->with('subject')->lock('WITH(NOLOCK)')->select('*'))
            ->editColumn('created_at', function (ActivityLog $activity) {
                return $activity->created_at->diffForHumans();
            })->editColumn('description', function (ActivityLog $activity) {
                return '<p><b>' . $activity->event . '</b><br>' . $activity->description . '</p>';
            })->rawColumns(['description'])->make();
    }
}
