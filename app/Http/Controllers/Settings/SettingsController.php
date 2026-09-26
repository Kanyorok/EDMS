<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Handle the incoming request.
     * @throws AuthorizationException
     */
    public function lists(): View
    {
        $this->authorize('view', CodeDetail::class);

        return view('settings.lists');
    }

    /**
     * Handle the incoming request.
     * @throws AuthorizationException
     */
    public function users(): View
    {
        $this->authorize('settings', User::class);

        return view('settings.users');
    }

}
