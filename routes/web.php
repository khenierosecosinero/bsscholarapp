<?php

use App\Http\Controllers\AnnouncementActionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentActionController;
use App\Http\Controllers\EventActionController;
use App\Http\Controllers\NotificationActionController;
use App\Http\Controllers\ProfileActionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffDocumentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::get('/register/staff', [AuthController::class, 'showStaffRegister'])->name('register.staff');
Route::post('/register/staff', [AuthController::class, 'registerStaff'])->name('register.staff.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

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
        return redirect()->route('staff.dashboard');
    }

    return redirect()->route('user.dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/locations', [AdminController::class, 'locations'])->name('locations');
    Route::post('/locations', [AdminController::class, 'storeLocation'])->name('locations.store');
    Route::get('/locations/{location}/edit', [AdminController::class, 'editLocation'])->name('locations.edit');
    Route::put('/locations/{location}', [AdminController::class, 'updateLocation'])->name('locations.update');
    Route::get('/scholars', [AdminController::class, 'scholars'])->name('scholars');
    Route::get('/staff', [AdminController::class, 'staff'])->name('staff');
    Route::get('/events', [AdminController::class, 'events'])->name('events');
    Route::get('/attendance', [AdminController::class, 'attendance'])->name('attendance');
    Route::get('/service-hours', [AdminController::class, 'serviceHours'])->name('service-hours');
    Route::get('/documents', [AdminController::class, 'documents'])->name('documents');
    Route::get('/participation', [AdminController::class, 'participation'])->name('participation');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
    Route::put('/settings/password', [AdminController::class, 'changePassword'])->name('settings.password');
});

Route::middleware(['auth', 'scholar.staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [StaffController::class, 'dashboard'])->name('dashboard');
    Route::get('/scholars', [StaffController::class, 'scholars'])->name('scholars');
    Route::get('/scholars/{scholar}', [StaffController::class, 'showScholar'])->name('scholars.show');
    Route::get('/events', [StaffController::class, 'events'])->name('events');
    Route::get('/events/create', [StaffController::class, 'createEvent'])->name('events.create');
    Route::post('/events', [StaffController::class, 'storeEvent'])->name('events.store');
    Route::get('/events/{event}', [StaffController::class, 'showEvent'])->name('events.show');
        Route::get('/attendance', [StaffController::class, 'attendance'])->name('attendance');
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
    Route::get('/reports/service-hours', [StaffController::class, 'serviceHoursReports'])->name('reports.service-hours');
    Route::get('/reports/attendance', [StaffController::class, 'attendanceReports'])->name('reports.attendance');
    Route::get('/reports/participation', [StaffController::class, 'participationReports'])->name('reports.participation');
    Route::get('/reports/completion', [StaffController::class, 'completionReports'])->name('reports.completion');
    Route::post('/scholars/{scholar}/approve', [StaffController::class, 'approveScholar'])->name('scholars.approve');
    Route::post('/scholars/{scholar}/reject', [StaffController::class, 'rejectScholar'])->name('scholars.reject');
});

Route::middleware(['auth', 'scholar'])->prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    Route::post('/dismiss-pending-modal', [UserController::class, 'dismissPendingModal'])->name('dismiss-pending-modal');
    Route::get('/announcements', [UserController::class, 'announcements'])->name('announcements');
    Route::get('/announcements/{announcement}', [UserController::class, 'announcementShow'])->name('announcements.show');
    Route::post('/announcements/read-all', [AnnouncementActionController::class, 'markAllRead'])->name('announcements.read-all');
    Route::post('/announcements/{announcement}/read', [AnnouncementActionController::class, 'markRead'])->name('announcements.read');

    Route::middleware('scholar.approved')->group(function () {
        Route::get('/events', [UserController::class, 'events'])->name('events');
        Route::get('/calendar', [UserController::class, 'calendar'])->name('calendar');
        Route::get('/service-hours', [UserController::class, 'serviceHours'])->name('service-hours');
        Route::get('/documents', [UserController::class, 'documents'])->name('documents');
        Route::get('/notifications', [UserController::class, 'notifications'])->name('notifications');
        Route::get('/profile', [UserController::class, 'profile'])->name('profile');

        Route::post('/events/{event}/register', [EventActionController::class, 'register'])->name('events.register');
        Route::post('/events/{event}/check-in', [EventActionController::class, 'checkIn'])->name('events.check-in');
        Route::post('/events/{event}/check-out', [EventActionController::class, 'checkOut'])->name('events.check-out');
        Route::post('/events/{event}/photo', [EventActionController::class, 'attachPhoto'])->name('events.photo');
        Route::get('/attendances/{attendance}/photo', [EventActionController::class, 'viewPhoto'])->name('attendances.photo');
        Route::post('/attendances/{attendance}/approve', [EventActionController::class, 'approveAttendance'])->name('attendances.approve');

        Route::post('/documents/upload', [DocumentActionController::class, 'upload'])->name('documents.upload');
        Route::get('/documents/{document}/download', [DocumentActionController::class, 'download'])->name('documents.download');

        Route::post('/notifications/{notification}/read', [NotificationActionController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationActionController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/settings', [NotificationActionController::class, 'updateSettings'])->name('notifications.settings');

        Route::put('/profile', [ProfileActionController::class, 'update'])->name('profile.update');
        Route::put('/profile/guardian', [ProfileActionController::class, 'updateGuardian'])->name('profile.guardian');
        Route::put('/profile/academic', [ProfileActionController::class, 'updateAcademicPreference'])->name('profile.academic');
        Route::put('/profile/academic/global', [ProfileActionController::class, 'updateGlobalAcademicSettings'])->name('profile.academic.global');
        Route::put('/profile/password', [ProfileActionController::class, 'changePassword'])->name('profile.password');
        Route::delete('/profile', [ProfileActionController::class, 'destroy'])->name('profile.destroy');
    });
});
