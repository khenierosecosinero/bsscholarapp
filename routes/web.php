<?php

use App\Http\Controllers\AnnouncementActionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\DocumentActionController;
use App\Http\Controllers\EventActionController;
use App\Http\Controllers\NotificationActionController;
use App\Http\Controllers\ProfileActionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffDocumentController;
use App\Http\Controllers\StaffSchoolController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->name('password.email');
    Route::get('/forgot-password/verify', [PasswordResetController::class, 'showVerifyForm'])->name('password.verify');
    Route::post('/forgot-password/verify', [PasswordResetController::class, 'verifyCode'])->name('password.verify.post');
    Route::post('/forgot-password/resend', [PasswordResetController::class, 'resendCode'])->name('password.resend');
    Route::get('/forgot-password/reset', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/forgot-password/reset', [PasswordResetController::class, 'updatePassword'])->name('password.update');
});
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::get('/register/staff', [AuthController::class, 'showStaffRegister'])->name('register.staff');
Route::post('/register/staff', [AuthController::class, 'registerStaff'])->name('register.staff.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/auth/google', [GoogleDriveController::class, 'redirectToGoogle'])
    ->name('google.redirect');

Route::get('/auth/google/callback', [GoogleDriveController::class, 'handleGoogleCallback'])
    ->name('google.callback');

Route::middleware('auth')->group(function () {
    Route::get('/google-drive', [GoogleDriveController::class, 'showTest'])
        ->name('google.drive.test');
    Route::post('/google-drive/upload', [GoogleDriveController::class, 'upload'])
        ->name('google.drive.upload');
});

Route::middleware('auth')->get('/events/{event}/image', [EventActionController::class, 'viewEventImage'])->name('events.image');

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user->isRejected()) {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('error', 'Your account has been rejected and can no longer access the system. Please contact Scholar Staff for assistance.');
    }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isScholarStaff()) {
            if ($user->isStaffPendingApproval() || $user->isStaffRejected()) {
                $isRejected = $user->isStaffRejected();

                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                $message = $isRejected
                    ? 'Your scholar staff account has been rejected and can no longer access the system. Please contact the system administrator for assistance.'
                    : 'Your scholar staff account is pending administrator approval. You cannot log in until your registration has been approved.';

                return redirect()
                    ->route('login')
                    ->with($isRejected ? 'error' : 'warning', $message);
            }

            return redirect()->route('staff.dashboard');
        }

    return redirect()->route('user.dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/sidebar-badges', [AdminController::class, 'sidebarBadges'])->name('sidebar-badges');
    Route::get('/scholars', [AdminController::class, 'scholars'])->name('scholars');
    Route::get('/scholars/{scholar}', [AdminController::class, 'showScholar'])->name('scholars.show');
    Route::get('/staff', [AdminController::class, 'staff'])->name('staff');
    Route::get('/events', [AdminController::class, 'events'])->name('events');
    Route::get('/events/{event}', [AdminController::class, 'showEvent'])->name('events.show');
    Route::get('/events/{event}/attendances/{attendance}/photo', [AdminController::class, 'viewAttendancePhoto'])->name('events.attendances.photo');
    Route::get('/service-hours', [AdminController::class, 'serviceHours'])->name('service-hours');
    Route::get('/documents', [AdminController::class, 'documents'])->name('documents');
    Route::get('/documents/scholars/{scholar}', [AdminController::class, 'showScholarDocuments'])->name('documents.scholar');
    Route::get('/documents/{document}/view', [AdminController::class, 'viewDocument'])->name('documents.view');
    Route::get('/documents/{document}/download', [AdminController::class, 'downloadDocument'])->name('documents.download');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
    Route::put('/settings/password', [AdminController::class, 'changePassword'])->name('settings.password');
    Route::post('/staff/{staffMember}/approve', [AdminController::class, 'approveStaff'])->name('staff.approve');
    Route::post('/staff/{staffMember}/reject', [AdminController::class, 'rejectStaff'])->name('staff.reject');
});

Route::middleware(['auth', 'scholar.staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/pending-approval', [StaffController::class, 'pendingApproval'])->name('pending-approval');
    Route::post('/dismiss-pending-modal', [StaffController::class, 'dismissPendingStaffModal'])->name('dismiss-pending-modal');

    Route::middleware('scholar.staff.approved')->group(function () {
    Route::get('/dashboard', [StaffController::class, 'dashboard'])->name('dashboard');
    Route::get('/scholars', [StaffController::class, 'scholars'])->name('scholars');
    Route::get('/scholar-presence', [StaffController::class, 'scholarPresence'])->name('scholars.presence');
    Route::get('/scholars/{scholar}', [StaffController::class, 'showScholar'])->name('scholars.show');
    Route::get('/events', [StaffController::class, 'events'])->name('events');
    Route::get('/events/create', [StaffController::class, 'createEvent'])->name('events.create');
    Route::post('/events', [StaffController::class, 'storeEvent'])->name('events.store');
    Route::get('/events/{event}', [StaffController::class, 'showEvent'])->name('events.show');
        Route::get('/attendance', [StaffController::class, 'attendance'])->name('attendance');
        Route::post('/events/{event}/attendance/open', [StaffController::class, 'openAttendance'])->name('attendance.open');
        Route::post('/events/{event}/attendance/close', [StaffController::class, 'closeAttendance'])->name('attendance.close');
        Route::get('/attendances/{attendance}/photo', [StaffController::class, 'viewAttendancePhoto'])->name('attendances.photo');
        Route::post('/attendances/{attendance}/approve', [StaffController::class, 'approveAttendance'])->name('attendances.approve');
        Route::post('/attendances/{attendance}/reject', [StaffController::class, 'rejectAttendance'])->name('attendances.reject');
    Route::get('/documents', [StaffDocumentController::class, 'index'])->name('documents');
    Route::get('/documents/create', [StaffDocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [StaffDocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/types/{documentType}', [StaffDocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/types/{documentType}/edit', [StaffDocumentController::class, 'edit'])->name('documents.edit');
    Route::put('/documents/types/{documentType}', [StaffDocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/types/{documentType}', [StaffDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{document}/view', [StaffDocumentController::class, 'view'])->name('documents.view');
    Route::get('/documents/{document}/download', [StaffDocumentController::class, 'download'])->name('documents.download');
    Route::patch('/documents/{document}/status', [StaffDocumentController::class, 'updateStatus'])->name('documents.status');
    Route::get('/approval-requests', [StaffController::class, 'approvalRequests'])->name('approval-requests');
    Route::get('/calendar', [StaffController::class, 'calendar'])->name('calendar');
    Route::get('/settings', [StaffController::class, 'settings'])->name('settings');
    Route::put('/settings/club', [StaffController::class, 'updateClubName'])->name('settings.club');
    Route::put('/settings/password', [StaffController::class, 'changePassword'])->name('settings.password');
    Route::get('/settings/schools/create', [StaffSchoolController::class, 'create'])->name('settings.schools.create');
    Route::post('/settings/schools', [StaffSchoolController::class, 'store'])->name('settings.schools.store');
    Route::get('/settings/schools/{school}/edit', [StaffSchoolController::class, 'edit'])->name('settings.schools.edit');
    Route::put('/settings/schools/{school}', [StaffSchoolController::class, 'update'])->name('settings.schools.update');
    Route::delete('/settings/schools/{school}', [StaffSchoolController::class, 'destroy'])->name('settings.schools.destroy');
    Route::get('/reports/service-hours', [StaffController::class, 'serviceHoursReports'])->name('reports.service-hours');
    Route::get('/reports/attendance', [StaffController::class, 'attendanceReports'])->name('reports.attendance');
    Route::get('/reports/participation', [StaffController::class, 'participationReports'])->name('reports.participation');
    Route::get('/reports/completion', [StaffController::class, 'completionReports'])->name('reports.completion');
    Route::post('/scholars/{scholar}/approve', [StaffController::class, 'approveScholar'])->name('scholars.approve');
    Route::post('/scholars/{scholar}/reject', [StaffController::class, 'rejectScholar'])->name('scholars.reject');
    });
});

Route::middleware(['auth', 'scholar'])->prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    Route::post('/presence', [UserController::class, 'presenceHeartbeat'])->name('presence');
    Route::post('/presence/leave', [UserController::class, 'presenceLeave'])->name('presence.leave');
    Route::post('/dismiss-pending-modal', [UserController::class, 'dismissPendingModal'])->name('dismiss-pending-modal');
    Route::get('/announcements', [UserController::class, 'announcements'])->name('announcements');
    Route::get('/announcements/{announcement}', [UserController::class, 'announcementShow'])->name('announcements.show');
    Route::post('/announcements/read-all', [AnnouncementActionController::class, 'markAllRead'])->name('announcements.read-all');
    Route::post('/announcements/{announcement}/read', [AnnouncementActionController::class, 'markRead'])->name('announcements.read');

    Route::middleware('scholar.approved')->group(function () {
        Route::get('/events', [UserController::class, 'events'])->name('events');
        Route::get('/attendance-status', [UserController::class, 'attendanceStatus'])->name('attendance.status');
        Route::get('/calendar', [UserController::class, 'calendar'])->name('calendar');
        Route::get('/service-hours', [UserController::class, 'serviceHours'])->name('service-hours');
        Route::get('/documents', [UserController::class, 'documents'])->name('documents');
        Route::get('/notifications', [UserController::class, 'notifications'])->name('notifications');
        Route::get('/notifications/more', [UserController::class, 'moreNotifications'])->name('notifications.more');
        Route::get('/profile', [UserController::class, 'profile'])->name('profile');

        Route::post('/events/{event}/register', [EventActionController::class, 'register'])->name('events.register');
        Route::post('/events/{event}/check-in', [EventActionController::class, 'checkIn'])->name('events.check-in');
        Route::post('/events/{event}/check-out', [EventActionController::class, 'checkOut'])->name('events.check-out');
        Route::post('/events/{event}/photo', [EventActionController::class, 'attachPhoto'])->name('events.photo');
        Route::get('/attendances/{attendance}/photo', [EventActionController::class, 'viewPhoto'])->name('attendances.photo');
        Route::post('/attendances/{attendance}/approve', [EventActionController::class, 'approveAttendance'])->name('attendances.approve');

        Route::post('/documents/upload', [DocumentActionController::class, 'upload'])->name('documents.upload');
        Route::get('/documents/{document}/view', [DocumentActionController::class, 'view'])->name('documents.view');
        Route::get('/documents/{document}/download', [DocumentActionController::class, 'download'])->name('documents.download');
        Route::delete('/documents/{document}', [DocumentActionController::class, 'destroy'])->name('documents.destroy');

        Route::post('/notifications/{notification}/read', [NotificationActionController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationActionController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/settings', [NotificationActionController::class, 'updateSettings'])->name('notifications.settings');

        Route::put('/profile', [ProfileActionController::class, 'update'])->name('profile.update');
        Route::post('/profile/avatar', [ProfileActionController::class, 'updateAvatar'])->name('profile.avatar');
        Route::put('/profile/guardian', [ProfileActionController::class, 'updateGuardian'])->name('profile.guardian');
        Route::put('/profile/academic', [ProfileActionController::class, 'updateAcademicPreference'])->name('profile.academic');
        Route::put('/profile/academic/global', [ProfileActionController::class, 'updateGlobalAcademicSettings'])->name('profile.academic.global');
        Route::put('/profile/password', [ProfileActionController::class, 'changePassword'])->name('profile.password');
        Route::delete('/profile', [ProfileActionController::class, 'destroy'])->name('profile.destroy');
    });
});
