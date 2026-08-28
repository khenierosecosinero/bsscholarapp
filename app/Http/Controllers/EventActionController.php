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

        if ($event->hasEnded()) {
            return back()->with('error', 'This event has already ended.');
        }

        EventRegistration::updateOrCreate(
            ['user_id' => $user->id, 'event_id' => $event->id],
            ['status' => EventRegistration::STATUS_CONFIRMED]
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
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->first();

        if (!$registration) {
            return back()->with('error', 'You must register for this event before checking in.');
        }

        $period = $this->academic->forUser($user);
        $stamp = $this->academic->attendanceStamp($period);

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'event_id' => $event->id],
            array_merge(['status' => Attendance::STATUS_PENDING], $stamp)
        );

        if ($attendance && $attendance->status === Attendance::STATUS_FAILED_CHECK_IN) {
            return back()->with('error', 'You failed to check in for this event. No service hours can be credited.');
        }

        if ($attendance->check_in) {
            return back()->with('error', 'You have already checked in.');
        }

        $attendance->update(array_merge(['check_in' => now()], $stamp));
        $this->scholar->logActivity($user, 'attendance', "Checked in for {$event->title}");
        $this->scholar->notify($user, 'Checked In', "You checked in for {$event->title}.", 'attendance');

        return back()->with('success', 'Check-in recorded. Attach a photo of your participation so Scholar Staff can verify your attendance.');
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
        $hours = $this->scholar->eventHourValue($event);

        $period = $this->academic->forUser($user);
        $stamp = $this->academic->attendanceStamp($period);

        $attendance->update(array_merge([
            'check_out' => $checkOut,
            'hours_earned' => $hours,
            'status' => Attendance::STATUS_PENDING,
        ], $stamp));

        $this->scholar->logActivity($user, 'attendance', "Checked out from {$event->title} ({$hours} hrs pending verification)");

        if (! $attendance->hasPhoto()) {
            $this->scholar->notify($user, 'Photo Required', "Check-out for {$event->title} was recorded. Attach a photo of your participation so Scholar Staff can verify your attendance.", 'attendance');

            return back()->with('success', 'Check-out recorded. Attach a photo of your participation to complete your attendance.');
        }

        $this->scholar->notify($user, 'Attendance Submitted', "Your attendance for {$event->title} is pending Scholar Staff verification. {$hours} service hours will be credited after approval.", 'attendance');

        return back()->with('success', "Check-out recorded. {$hours} service hours are pending Scholar Staff verification.");
    }

    public function attachPhoto(Request $request, Event $event)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ], [
            'photo.required' => 'Please choose a photo of your participation.',
            'photo.image' => 'The attachment must be a photo (JPG or PNG).',
            'photo.mimes' => 'The photo must be a JPG or PNG file.',
            'photo.max' => 'The photo must not be larger than 5MB.',
        ]);

        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->first();

        if (! $attendance || ! $attendance->hasCheckedIn()) {
            return back()->with('error', 'Check in first, then attach a photo of your participation.');
        }

        try {
            $this->scholar->storeAttendancePhoto($attendance, $request->file('photo'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Participation photo uploaded. Scholar Staff will use it to verify your attendance.');
    }

    public function viewPhoto(Attendance $attendance)
    {
        abort_unless($attendance->user_id === Auth::id(), 403);

        return $attendance->photoResponse();
    }

    public function approveAttendance(Request $request, Attendance $attendance)
    {
        $user = Auth::user();

        if (! $user->isAdmin()) {
            abort(403);
        }

        if ($attendance->status === Attendance::STATUS_APPROVED) {
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
