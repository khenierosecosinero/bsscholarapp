<?php

namespace App\Http\Controllers;

use App\Models\ScholarNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationActionController extends Controller
{
    public function markRead(ScholarNotification $notification)
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function markAllRead(Request $request)
    {
        Auth::user()->scholarNotifications()->where('is_read', false)->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        $prefs = $user->defaultNotificationPreferences();

        foreach (array_keys($prefs) as $key) {
            $prefs[$key] = $request->boolean($key);
        }

        $user->update(['notification_preferences' => $prefs]);

        return back()->with('success', 'Notification settings saved.');
    }
}
