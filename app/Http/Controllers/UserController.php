<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\UserActivity;
use App\Models\Announcement;
use App\Models\DocumentType;
use App\Support\CourseCatalog;
use App\Services\AcademicSettingsService;
use App\Services\AnnouncementService;
use App\Services\AttendanceSessionService;
use App\Services\GoogleDriveService;
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
        private AttendanceSessionService $attendanceSessions,
        private GoogleDriveService $drive,
    ) {}

    private function layoutData(string $active, string $title, string $subtitle = ''): array
    {
        $user = Auth::user()->loadMissing(['scholarshipProgram', 'scholarshipClub']);
        $hasPortalAccess = $user->hasScholarPortalAccess();
        $needsHours = $active !== 'notifications';
        $needsDocuments = $hasPortalAccess && in_array($active, ['dashboard', 'documents'], true);
        $needsCalendar = $active === 'profile';
        $needsSync = $hasPortalAccess && in_array($active, ['dashboard', 'events', 'calendar', 'service-hours'], true);

        if ($needsSync) {
            $this->scholar->syncMissedCheckInsForUser($user);
            $this->scholar->syncCompletedEventHoursForUser($user);
        }

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

        if ($needsDocuments) {
            $documents = $this->scholar->documentsForUser($user);
            $documentOverview = $this->scholar->documentOverviewStats($user, $documents);
            if ($active === 'documents') {
                $documentTypes = DocumentType::query()
                    ->where('scholarship_program_id', $user->scholarship_program_id)
                    ->orderBy('name')
                    ->get();
            }
        }

        $hourStats = $needsHours
            ? $this->scholar->serviceHourStats($user)
            : [
                'approved' => 0,
                'pending' => 0,
                'required' => ScholarService::REQUIRED_HOURS,
                'remaining' => ScholarService::REQUIRED_HOURS,
            ];

        return [
            'user' => $user,
            'scholarshipProgram' => $program,
            'active' => $active,
            'pageTitle' => $title,
            'pageSubtitle' => $resolvedSubtitle,
            'hourStats' => $hourStats,
            'semesterInfo' => $needsHours
                ? $this->scholar->currentSemesterInfo($user, $hourStats)
                : [],
            'globalAcademicSettings' => $this->academic->current(),
            'academicYearOptions' => $this->academic->yearOptions(),
            'semesterOptions' => AcademicSettingsService::SEMESTERS,
            'sidebarCalendarEvents' => $needsCalendar
                ? $this->scholar->calendarEvents($user, $year, $month)
                : collect(),
            'sidebarMonthDate' => $sidebarMonthDate,
            'sidebarPrevMonth' => $sidebarMonthDate->copy()->subMonth(),
            'sidebarNextMonth' => $sidebarMonthDate->copy()->addMonth(),
            'documents' => $documents,
            'documentTypes' => $documentTypes,
            'documentOverview' => $documentOverview,
            'unreadNotificationsCount' => $user->unreadNotificationCount(),
        ];
    }

    public function dashboard(Request $request)
    {
        $user = Auth::user();
        $layout = $this->layoutData(
            'dashboard',
            'Dashboard',
            ''
        );

        return view('user.dashboard', array_merge($layout, [
            'stats' => $this->scholar->dashboardStats(
                $user,
                $layout['hourStats'],
                $layout['unreadNotificationsCount']
            ),
            'events' => $this->scholar->upcomingEvents($user, 5),
            'activities' => UserActivity::where('user_id', $user->id)->latest()->limit(5)->get(),
            'announcements' => $this->announcements->forUser($user, 5),
            'announcementStats' => $this->announcements->statsForUser($user),
            'pendingAttendances' => $this->academic->scopeAttendancesForPeriod(
                $user->attendances()->with('event')->where('status', 'pending')->whereNotNull('check_in'),
                $this->academic->forUser($user)
            )->get(),
            'dashboardDocuments' => $layout['documents'],
            'calendarEvents' => $this->scholar->calendarEvents($user, now()->year, now()->month),
            'attendanceSessions' => $user->hasScholarPortalAccess()
                ? $this->attendanceSessions->sessionsForUser($user)
                : collect(),
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
            'attendanceSessions' => $this->attendanceSessions->sessionsForUser($user),
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
        $period = $this->academic->forUser($user);

        return view('user.documents', array_merge(
            $this->layoutData(
                'documents',
                'Documents',
                'Upload and manage your required documents.'
            ),
            [
                'activeTab' => $request->get('tab', 'all'),
                'scholarCode' => $user->scholar_id,
                'scholarName' => $user->full_name,
                'academicYearLabel' => $period->year_start.'-'.$period->year_end,
                'driveReady' => $this->drive->isConnected(),
            ]
        ));
    }

    public function notifications(Request $request)
    {
        $user = Auth::user();
        $filter = $request->get('filter', 'all');
        $pageSize = 5;

        $baseQuery = $user->scholarNotifications();
        $query = $this->filteredNotificationQuery($user, $filter);
        $filteredTotal = (clone $query)->count();
        $notifications = (clone $query)->limit($pageSize)->get();
        $hasMoreNotifications = $filteredTotal > $pageSize;

        return view('user.notifications', array_merge($this->layoutData(
            'notifications',
            'Notifications',
            'Stay updated with the latest announcements, reminders, and updates.'
        ), [
            'notifications' => $notifications,
            'extraNotifications' => collect(),
            'filteredTotal' => $filteredTotal,
            'hasMoreNotifications' => $hasMoreNotifications,
            'notificationPreviewCount' => $pageSize,
            'notifStats' => [
                'unread' => (clone $baseQuery)->where('is_read', false)->count(),
                'read' => (clone $baseQuery)->where('is_read', true)->count(),
                'important' => (clone $baseQuery)->where('is_important', true)->count(),
                'total' => (clone $baseQuery)->count(),
            ],
            'activeFilter' => $filter,
            'preferences' => $user->notificationPreferences(),
        ]));
    }

    public function moreNotifications(Request $request)
    {
        $user = Auth::user();
        $filter = $request->get('filter', 'all');
        $offset = max(0, (int) $request->get('offset', 5));
        $query = $this->filteredNotificationQuery($user, $filter);
        $total = (clone $query)->count();
        $notifications = $query->skip($offset)->get();

        $html = $notifications->map(
            fn ($notif) => view('partials.user-notification-item', [
                'notif' => $notif,
                'isExtra' => true,
            ])->render()
        )->implode('');

        return response()->json([
            'html' => $html,
            'count' => $notifications->count(),
            'total' => $total,
            'shown' => $offset + $notifications->count(),
        ]);
    }

    private function filteredNotificationQuery($user, string $filter)
    {
        $query = $user->scholarNotifications()->latest();

        if ($filter === 'unread') {
            $query->where('is_read', false);
        } elseif ($filter === 'important') {
            $query->where('is_important', true);
        }

        return $query;
    }

    public function profile(Request $request)
    {
        $layout = $this->layoutData(
            'profile',
            'Profile & Settings',
            'Manage your personal information, account settings, and preferences.'
        );

        $user = $layout['user'];

        $allowedTabs = ['profile-info', 'academic-settings', 'account-settings', 'security'];
        $activeProfileTab = (string) $request->query('tab', session('profile_tab', 'profile-info'));

        if (! in_array($activeProfileTab, $allowedTabs, true)) {
            $activeProfileTab = 'profile-info';
        }

        $user->loadMissing('scholarshipClub.schools');

        return view('user.profile', array_merge($layout, [
            'municipalityOptions' => ScholarshipProgram::municipalityOptions(
                $user->provinceName(),
                $user->municipalityName()
            ),
            'clubSchools' => $user->scholarshipClub?->schools ?? collect(),
            'yearLevels' => CourseCatalog::yearLevels(),
            'activeProfileTab' => $activeProfileTab,
        ]));
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
        $this->scholar->assertAnnouncementVisibleToUser($announcement, $user);
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

    public function attendanceStatus(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->hasScholarPortalAccess(), 403);

        $eventId = (int) $request->get('event', 0);

        return response()->json($this->attendanceSessions->liveStatusForUser($user, $eventId ?: null));
    }

    public function presenceHeartbeat(Request $request)
    {
        $user = Auth::user();
        abort_unless($user?->isScholar() && $user->canLogin(), 403);

        $user->markPresence();

        return response()->json([
            'ok' => true,
            'online' => true,
        ]);
    }

    public function presenceLeave(Request $request)
    {
        $user = Auth::user();
        if ($user?->isScholar()) {
            $user->clearPresence();
        }

        return response()->json(['ok' => true, 'online' => false]);
    }
}
