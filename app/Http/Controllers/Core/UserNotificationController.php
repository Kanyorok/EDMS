<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Communication\UserNotification;

class UserNotificationController extends Controller
{
    public function index()
    {
        $rows = UserNotification::where('UserID', auth()->id())
            ->orderByDesc('CreatedOn')
            ->paginate(30);

        return view('notifications.index', compact('rows'));
    }

    public function read(int $id)
    {
        UserNotification::where('Id', $id)
            ->where('UserID', auth()->id())
            ->whereNull('ReadOn')
            ->update(['ReadOn' => now()]);

        return back();
    }

    public function readAll()
    {
        UserNotification::where('UserID', auth()->id())
            ->whereNull('ReadOn')
            ->update(['ReadOn' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function clear()
    {
        UserNotification::where('UserID', auth()->id())->delete();

        return back()->with('success', 'Notifications cleared.');
    }
}
