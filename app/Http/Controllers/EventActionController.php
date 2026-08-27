<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\AcademicSettingsService;
use App\Services\ScholarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventActionController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private AcademicSettingsService $academic,
    ) {}

    public function register(Request $request, Event $event)
    {
        $user = Auth::user();

        EventRegistration::updateOrCreate(
            ['user_id' => $user->id, 'event_id' => $event->id],
            ['status' => 'confirmed']
        );

        $this->scholar->logActivity($user, 'event', "You confirmed participation to {$event->title}");
        $this->scholar->notify($user, 'Event Confirmed', "You are confirmed for {$event->title}.", 'event_reminder');

        return back()->with('success', 'You have successfully registered for this event.');
    }

    public function checkIn(Request $request, Event $event)
    {
        $user = Auth::user();

        if (!$event->isHappeningNow()) {
            return back()->with('error', 'Check-in is only available during the event time.');
        }

        $registration = EventRegistration::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->first();

        if (!$registration) {
            return back()->with('error', 'You must register for this event before checking in.');
        }

        $period = $this->academic->current();
        $stamp = $this->academic->attendanceStamp($period);

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'event_id' => $event->id],
            array_merge(['status' => 'pending'], $stamp)
        );

        if ($attendance->check_in) {
            return back()->with('error', 'You have already checked in.');
        }

        $attendance->update(array_merge(['check_in' => now()], $stamp));
        $this->scholar->logActivity($user, 'attendance', "Checked in for {$event->title}");
        $this->scholar->notify($user, 'Checked In', "You checked in for {$event->title}.", 'attendance');

        return back()->with('success', 'Check-in recorded successfully.');
    }

    public function checkOut(Request $request, Event $event)
    {
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->first();

        if (!$attendance || !$attendance->check_in) {
            return back()->with('error', 'You must check in first.');
        }

        if ($attendance->check_out) {
            return back()->with('error', 'You have already checked out.');
        }

        $checkOut = now();
        $hours = round($attendance->check_in->diffInMinutes($checkOut) / 60, 2);
        $hours = min($hours, (float) $event->service_hours);

        $period = $this->academic->current();
        $stamp = $this->academic->attendanceStamp($period);

        $attendance->update(array_merge([
            'check_out' => $checkOut,
            'hours_earned' => $hours,
            'status' => 'pending',
        ], $stamp));

        $this->scholar->logActivity($user, 'attendance', "Checked out from {$event->title} ({$hours} hrs)");
        $this->scholar->notify($user, 'Attendance Submitted', "Your attendance for {$event->title} is pending verification.", 'attendance');

        return back()->with('success', 'Check-out recorded. Hours pending verification.');
    }

    public function approveAttendance(Request $request, Attendance $attendance)
    {
        $user = Auth::user();

        if (! $user->isAdmin()) {
            abort(403);
        }

        if ($attendance->status === 'approved') {
            return back()->with('info', 'Participation is already confirmed for this event.');
        }

        try {
            $this->scholar->confirmParticipation($attendance);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Participation confirmed successfully.');
    }
}
