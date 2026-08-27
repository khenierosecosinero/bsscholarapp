<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnouncementActionController extends Controller
{
    public function __construct(private AnnouncementService $announcements) {}

    public function markRead(Announcement $announcement)
    {
        $this->ensurePublished($announcement);
        $this->announcements->markAsRead(Auth::user(), $announcement);

        return back()->with('success', 'Announcement marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $this->announcements->markAllAsRead(Auth::user());

        return back()->with('success', 'All announcements marked as read.');
    }

    private function ensurePublished(Announcement $announcement): void
    {
        if ($announcement->published_at && $announcement->published_at->isFuture()) {
            abort(404);
        }
    }
}
