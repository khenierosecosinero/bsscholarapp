<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Event;
use App\Models\UserActivity;
use App\Models\Announcement;
use App\Models\DocumentType;
use App\Services\AcademicSettingsService;
use App\Services\AnnouncementService;
use App\Services\ScholarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private AnnouncementService $announcements,
        private AcademicSettingsService $academic,
    ) {}

    private function layoutData(string $active, string $title, string $subtitle = ''): array
    {
        $user = Auth::user()->load('scholarshipProgram');
        $this->scholar->ensureUserDocuments($user);
        $this->scholar->syncMissedCheckInsForUser($user);
        $this->scholar->syncCompletedEventHoursForUser($user);

        $year = (int) request()->get('year', now()->year);
        $month = (int) request()->get('month', now()->month);
        $sidebarMonthDate = Carbon::create($year, $month, 1);

        $program = $user->scholarshipProgram;
        $resolvedSubtitle = $subtitle !== ''
            ? $subtitle
            : ($program?->name ?? 'Scholar Dashboard');

        $documentOverview = null;
        $documents = collect();
        $documentTypes = collect();

        if ($user->hasScholarPortalAccess()) {
            $documents = $this->scholar->documentsForUser($user);
            $documentOverview = $this->scholar->documentOverviewStats($user);
            $documentTypes = DocumentType::query()->orderBy('name')->get();
        }

        return [
            'user' => $user,
            'scholarshipProgram' => $program,
            'active' => $active,
            'pageTitle' => $title,
            'pageSubtitle' => $resolvedSubtitle,
            'hourStats' => $this->scholar->serviceHourStats($user),
            'semesterInfo' => $this->scholar->currentSemesterInfo($user),
            'globalAcademicSettings' => $this->academic->current(),
            'academicYearOptions' => $this->academic->yearOptions(),
            'semesterOptions' => AcademicSettingsService::SEMESTERS,
            'sidebarCalendarEvents' => $this->scholar->calendarEvents($user, $year, $month),
            'sidebarMonthDate' => $sidebarMonthDate,
            'sidebarPrevMonth' => $sidebarMonthDate->copy()->subMonth(),
            'sidebarNextMonth' => $sidebarMonthDate->copy()->addMonth(),
            'documents' => $documents,
            'documentTypes' => $documentTypes,
            'documentOverview' => $documentOverview,
        ];
    }

    public function dashboard(Request $request)
    {
        $user = Auth::user();

        return view('user.dashboard', array_merge($this->layoutData(
            'dashboard',
            'Welcome, ' . $user->full_name . '!',
            'Thank you for continuing to serve our community.'
        ), [
            'stats' => $this->scholar->dashboardStats($user),
            'events' => $this->scholar->upcomingEvents($user, 5),
            'activities' => UserActivity::where('user_id', $user->id)->latest()->limit(5)->get(),
            'announcements' => $this->announcements->forUser($user, 5),
            'announcementStats' => $this->announcements->statsForUser($user),
            'pendingAttendances' => $this->academic->scopeAttendancesForPeriod(
                $user->attendances()->with('event')->where('status', 'pending')->whereNotNull('check_in'),
                $this->academic->forUser($user)
            )->get(),
            'dashboardDocuments' => $this->dashboardDocuments($user),
            'calendarEvents' => $this->scholar->calendarEvents($user, now()->year, now()->month),
            'showPendingApprovalModal' => $user->isPendingApproval() && $request->session()->get('show_pending_approval_modal', false),
        ]));
    }

    public function dismissPendingModal(Request $request)
    {
        $request->session()->forget('show_pending_approval_modal');

        return response()->noContent();
    }

    public function events(Request $request)
    {
        $user = Auth::user();
        $events = $this->scholar->eventsForUser(
            $user,
            $request->get('search'),
            $request->get('status', 'upcoming')
        );

        $selectedId = $request->get('event', $events->first()['id'] ?? null);
        $selected = $events->firstWhere('id', (int) $selectedId) ?? $events->first();

        return view('user.events', array_merge($this->layoutData(
            'events',
            'Events',
            'Browse and join events. Earn service hours and make an impact.'
        ), [
            'events' => $events,
            'selected' => $selected,
            'upcomingEvent' => $this->scholar->nextUpcomingEvent($user),
        ]));
    }

    public function calendar(Request $request)
    {
        $user = Auth::user();
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $monthDate = Carbon::create($year, $month, 1);
        $events = $this->scholar->calendarEvents($user, $year, $month);

        $selectedId = $request->get('event');
        $selected = $selectedId
            ? $events->firstWhere('id', (int) $selectedId)
            : $events->first();

        return view('user.calendar', array_merge($this->layoutData(
            'calendar',
            'Calendar of Activities',
            'View upcoming events and your participation schedule.'
        ), [
            'events' => $events,
            'selected' => $selected,
            'monthDate' => $monthDate,
            'prevMonth' => $monthDate->copy()->subMonth(),
            'nextMonth' => $monthDate->copy()->addMonth(),
            'upcomingEvents' => $this->scholar->upcomingEvents($user, 5),
            'scheduleStats' => $this->scholar->scheduleSummaryStats($events),
        ]));
    }

    public function serviceHours(Request $request)
    {
        $user = Auth::user();
        $status = $request->get('tab', 'all');
        $period = $this->academic->forUser($user);

        $query = $this->academic->scopeAttendancesForPeriod(
            $user->attendances()->with('event')->latest('created_at'),
            $period
        );
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('user.service-hours', array_merge($this->layoutData(
            'service-hours',
            'Service Hours',
            'Track your service hours progress for the selected semester.'
        ), [
            'records' => $query->get(),
            'activeTab' => $status,
            'semesterSummary' => $this->scholar->semesterSummaryChart($user),
            'semesterHoursChart' => $this->scholar->semesterHoursComparison($user),
        ]));
    }

    public function documents(Request $request)
    {
        $user = Auth::user();

        return view('user.documents', array_merge(
            $this->layoutData(
                'documents',
                'Documents',
                'Upload and manage your required documents.'
            ),
            ['activeTab' => $request->get('tab', 'all')]
        ));
    }

    public function notifications(Request $request)
    {
        $user = Auth::user();
        $filter = $request->get('filter', 'all');

        $query = $user->scholarNotifications()->latest();
        if ($filter === 'unread') {
            $query->where('is_read', false);
        } elseif ($filter === 'important') {
            $query->where('is_important', true);
        }

        $all = $user->scholarNotifications()->get();

        return view('user.notifications', array_merge($this->layoutData(
            'notifications',
            'Notifications',
            'Stay updated with the latest announcements, reminders, and updates.'
        ), [
            'notifications' => $query->get(),
            'notifStats' => [
                'unread' => $all->where('is_read', false)->count(),
                'read' => $all->where('is_read', true)->count(),
                'important' => $all->where('is_important', true)->count(),
                'total' => $all->count(),
            ],
            'activeFilter' => $filter,
            'preferences' => $user->notificationPreferences(),
        ]));
    }

    public function profile(Request $request)
    {
        return view('user.profile', $this->layoutData(
            'profile',
            'Profile & Settings',
            'Manage your personal information, account settings, and preferences.'
        ));
    }

    public function announcements(Request $request)
    {
        $user = Auth::user();
        $filter = $request->get('filter', 'all');

        return view('user.announcements', array_merge($this->layoutData(
            'announcements',
            'Announcements',
            'Read official updates and important notices for all scholars.'
        ), [
            'announcements' => $this->announcements->forUser($user, null, $filter),
            'announcementStats' => $this->announcements->statsForUser($user),
            'activeFilter' => $filter,
        ]));
    }

    private function dashboardDocuments($user)
    {
        return $this->scholar->documentsForUser($user);
    }

    public function announcementShow(Request $request, Announcement $announcement)
    {
        if ($announcement->published_at && $announcement->published_at->isFuture()) {
            abort(404);
        }

        $user = Auth::user();
        $this->announcements->markAsRead($user, $announcement);
        $announcement->setAttribute('is_read', true);

        return view('user.announcement-show', array_merge($this->layoutData(
            'announcements',
            $announcement->title,
            'Official announcement from Batang Surigaonon Scholar\'s App.'
        ), [
            'announcement' => $announcement,
            'announcementStats' => $this->announcements->statsForUser($user),
        ]));
    }
}
