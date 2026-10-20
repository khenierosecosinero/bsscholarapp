<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\ScholarNotification;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EventStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_status_dropdown_updates_pending_confirmed_and_cancelled(): void
    {
        [$staff, $event] = $this->makeStaffWithConfirmedEvent();

        $html = $this->actingAs($staff)
            ->get(route('staff.events'))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee('>View</a>', false)
            ->assertSee('name="status"', false)
            ->assertSee('staff-event-status-select', false)
            ->assertSee('>Pending</option>', false)
            ->assertSee('>Confirmed</option>', false)
            ->assertSee('>Cancelled</option>', false)
            ->assertDontSee('>Pending</button>')
            ->assertDontSee('>Confirmed</button>')
            ->assertDontSee('title="Cancel this event">Cancelled</button>')
            ->assertSee('>More</button>', false)
            ->assertSee(route('staff.events.show', $event), false)
            ->getContent();

        $this->assertStringContainsString('selected', $html);
        $this->assertStringContainsString('value="confirmed"', $html);
        $this->assertStringContainsString('data-confirm-title="Cancel this event?"', $html);
        $this->assertStringContainsString('Only cancelled events can be removed', $html);

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_PENDING])
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('success');

        $this->assertSame(Event::STATUS_PENDING, $event->fresh()->status);

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CONFIRMED])
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('success');

        $this->assertSame(Event::STATUS_CONFIRMED, $event->fresh()->status);

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CANCELLED])
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('success');

        $this->assertSame(Event::STATUS_CANCELLED, $event->fresh()->status);

        $cancelledHtml = $this->actingAs($staff)
            ->get(route('staff.events', ['status' => 'cancelled']))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee('Cancelled')
            ->getContent();

        $this->assertStringContainsString('disabled', $cancelledHtml);
        $this->assertStringContainsString('data-confirm-title="Remove this event?"', $cancelledHtml);
        $this->assertStringContainsString('>Remove Event</button>', $cancelledHtml);
    }

    public function test_only_cancelled_events_can_be_removed(): void
    {
        [$staff, $event] = $this->makeStaffWithConfirmedEvent();

        $this->actingAs($staff)
            ->delete(route('staff.events.destroy', $event))
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id]);

        $event->update(['status' => Event::STATUS_COMPLETED]);

        $this->actingAs($staff)
            ->delete(route('staff.events.destroy', $event))
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id]);

        $event->update(['status' => Event::STATUS_PENDING]);

        $this->actingAs($staff)
            ->delete(route('staff.events.destroy', $event))
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id]);

        $event->update(['status' => Event::STATUS_CANCELLED]);

        $this->actingAs($staff)
            ->delete(route('staff.events.destroy', $event))
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_deleting_an_event_does_not_leave_its_records_on_a_new_event_with_the_same_title(): void
    {
        Storage::fake('public');
        [$staff, $event, $scholar] = $this->makeStaffWithConfirmedEvent([], true);
        $event->update(['status' => Event::STATUS_CANCELLED]);

        Storage::disk('public')->put('event_images/old-banner.png', 'old-banner');
        Storage::disk('public')->put('attendance_photos/old-check-in.png', 'old-photo');
        $event->update(['image_path' => 'event_images/old-banner.png']);

        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);
        Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => Attendance::STATUS_APPROVED,
            'hours_earned' => 4,
            'check_in' => now()->subHour(),
            'photo_path' => 'attendance_photos/old-check-in.png',
        ]);
        $notification = ScholarNotification::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'title' => 'Old event notice',
            'body' => 'This notice belongs to the removed event.',
            'category' => 'event_reminder',
            'is_read' => false,
            'is_important' => false,
        ]);

        $oldId = $event->id;

        $this->actingAs($staff)
            ->delete(route('staff.events.destroy', $event))
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('events', ['id' => $oldId]);
        $this->assertDatabaseMissing('event_registrations', ['event_id' => $oldId]);
        $this->assertDatabaseMissing('attendances', ['event_id' => $oldId]);
        $this->assertNull($notification->fresh()->event_id);
        Storage::disk('public')->assertMissing('event_images/old-banner.png');
        Storage::disk('public')->assertMissing('attendance_photos/old-check-in.png');

        $replacement = Event::create([
            'title' => $event->title,
            'location' => 'New location',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'service_hours' => 2,
            'status' => Event::STATUS_PENDING,
            'scholarship_program_id' => $event->scholarship_program_id,
            'attendance_is_open' => false,
        ]);

        $this->assertNotSame($oldId, $replacement->id);
        $this->assertDatabaseMissing('event_registrations', ['event_id' => $replacement->id]);
        $this->assertDatabaseMissing('attendances', ['event_id' => $replacement->id]);
        $this->assertNull($replacement->image_path);

        $this->actingAs($staff)
            ->get(route('staff.events.show', $replacement))
            ->assertOk()
            ->assertDontSee($scholar->full_name)
            ->assertDontSee('old-check-in.png');
    }

    public function test_status_dropdown_rejects_invalid_transitions(): void
    {
        [$staff, $event] = $this->makeStaffWithConfirmedEvent();

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CONFIRMED])
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('success');

        $this->assertSame(Event::STATUS_CONFIRMED, $event->fresh()->status);

        $event->update(['status' => Event::STATUS_CANCELLED]);

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_PENDING])
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('error');

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CONFIRMED])
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('error');

        $this->assertSame(Event::STATUS_CANCELLED, $event->fresh()->status);
    }

    public function test_confirmed_events_auto_complete_when_end_time_passes(): void
    {
        [$staff, $confirmed] = $this->makeStaffWithConfirmedEvent([
            'title' => 'Past Confirmed Event',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);

        $pending = Event::create([
            'title' => 'Past Pending Event',
            'location' => 'Hall',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
            'service_hours' => 2,
            'status' => Event::STATUS_PENDING,
            'scholarship_program_id' => $confirmed->scholarship_program_id,
        ]);

        $cancelled = Event::create([
            'title' => 'Past Cancelled Event',
            'location' => 'Hall',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
            'service_hours' => 2,
            'status' => Event::STATUS_CANCELLED,
            'scholarship_program_id' => $confirmed->scholarship_program_id,
        ]);

        $this->assertSame(1, Event::completeExpiredConfirmed());
        $this->assertSame(Event::STATUS_COMPLETED, $confirmed->fresh()->status);
        $this->assertSame(Event::STATUS_PENDING, $pending->fresh()->status);
        $this->assertSame(Event::STATUS_CANCELLED, $cancelled->fresh()->status);

        $this->assertSame(0, Event::completeExpiredConfirmed());

        Artisan::call('events:complete-expired');
        $this->assertSame(0, Event::completeExpiredConfirmed());

        $this->actingAs($staff)
            ->get(route('staff.events', ['status' => 'completed']))
            ->assertOk()
            ->assertSee('Past Confirmed Event')
            ->assertSee('Completed')
            ->assertDontSee('Past Pending Event')
            ->assertDontSee('Past Cancelled Event');
    }

    public function test_sync_status_from_schedule_never_completes_pending_or_cancelled(): void
    {
        $program = $this->makeProgram();

        $pending = Event::create([
            'title' => 'Pending Sync',
            'location' => 'Hall',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'service_hours' => 1,
            'status' => Event::STATUS_PENDING,
            'scholarship_program_id' => $program->id,
        ]);

        $cancelled = Event::create([
            'title' => 'Cancelled Sync',
            'location' => 'Hall',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinute(),
            'service_hours' => 1,
            'status' => Event::STATUS_CANCELLED,
            'scholarship_program_id' => $program->id,
        ]);

        $pending->syncStatusFromSchedule();
        $cancelled->syncStatusFromSchedule();

        $this->assertSame(Event::STATUS_PENDING, $pending->fresh()->status);
        $this->assertSame(Event::STATUS_CANCELLED, $cancelled->fresh()->status);
    }

    public function test_staff_cannot_cancel_event_outside_their_program(): void
    {
        [$staff] = $this->makeStaffWithConfirmedEvent();
        $otherProgram = ScholarshipProgram::create([
            'location_name' => 'Tandag',
            'location_type' => 'city_municipality',
            'name' => 'Tandag, Surigao del Sur',
            'display_name' => 'Tandag',
            'province_name' => 'Surigao del Sur',
            'slug' => 'tandag-event-cancel',
            'is_active' => true,
        ]);

        $otherEvent = Event::create([
            'title' => 'Other Program Event',
            'location' => 'Gym',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'service_hours' => 2,
            'status' => Event::STATUS_CONFIRMED,
            'scholarship_program_id' => $otherProgram->id,
        ]);

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $otherEvent), ['status' => Event::STATUS_CANCELLED])
            ->assertForbidden();

        $this->assertSame(Event::STATUS_CONFIRMED, $otherEvent->fresh()->status);
    }

    public function test_scholar_sees_event_created_before_their_activation_if_still_ongoing(): void
    {
        [$staff, $event, $scholar] = $this->makeStaffWithConfirmedEvent([
            'title' => 'Created Before Approval',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
        ], true);

        // Simulate scholar becoming active after the event was already created.
        $scholar->forceFill(['events_visible_from' => now()->addMinute()])->save();
        $this->assertTrue($event->fresh()->created_at->lt($scholar->fresh()->events_visible_from));

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'all', 'event' => $event->id]))
            ->assertOk()
            ->assertSee('Created Before Approval')
            ->assertSee('Confirmed');

        $this->actingAs($scholar)
            ->get(route('user.calendar', [
                'year' => $event->starts_at->year,
                'month' => $event->starts_at->month,
                'event' => $event->id,
            ]))
            ->assertOk()
            ->assertSee('Created Before Approval');
    }

    public function test_scholar_sees_staff_event_status_changes_including_cancelled(): void
    {
        [$staff, $event, $scholar] = $this->makeStaffWithConfirmedEvent([], true);

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'all', 'event' => $event->id]))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee('Confirmed');

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_PENDING])
            ->assertRedirect(route('staff.events'));

        $this->assertSame(Event::STATUS_PENDING, $event->fresh()->status);

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'pending', 'event' => $event->id]))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee('Pending');

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CONFIRMED])
            ->assertRedirect(route('staff.events'));

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CANCELLED])
            ->assertRedirect(route('staff.events'));

        $this->assertSame(Event::STATUS_CANCELLED, $event->fresh()->status);

        $this->assertDatabaseHas('scholar_notifications', [
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'title' => 'Event Cancelled: '.$event->title,
        ]);

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'cancelled', 'event' => $event->id]))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee('Cancelled')
            ->assertSee('Event Cancelled')
            ->assertDontSee('Attend / Register');

        $this->actingAs($scholar)
            ->get(route('user.notifications'))
            ->assertOk()
            ->assertSee('Event Cancelled: '.$event->title)
            ->assertSee('View Event');

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.register', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        // Cancelling again must not create a duplicate notification row.
        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CANCELLED]);

        $this->assertSame(
            1,
            \App\Models\ScholarNotification::query()
                ->where('user_id', $scholar->id)
                ->where('event_id', $event->id)
                ->where('title', 'Event Cancelled: '.$event->title)
                ->count()
        );
    }

    public function test_scholar_cannot_register_or_attend_pending_cancelled_or_completed_events(): void
    {
        [$staff, $event, $scholar] = $this->makeStaffWithConfirmedEvent([
            'title' => 'Lifecycle Gate Event',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(5),
        ], true);

        $scholar->forceFill(['events_visible_from' => now()->subYear()])->save();

        $event->update(['status' => Event::STATUS_PENDING]);

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'pending', 'event' => $event->id]))
            ->assertOk()
            ->assertSee('Registration is unavailable because this event is pending approval. Please wait until the event is confirmed.')
            ->assertDontSee('Attend / Register');

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.register', $event))
            ->assertRedirect()
            ->assertSessionHas('error', 'Registration is unavailable because this event is pending approval. Please wait until the event is confirmed.');

        $this->assertDatabaseMissing('event_registrations', [
            'user_id' => $scholar->id,
            'event_id' => $event->id,
        ]);

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-in', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $scholar->id,
            'event_id' => $event->id,
        ]);

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CONFIRMED])
            ->assertRedirect(route('staff.events'));

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'confirmed', 'event' => $event->id]))
            ->assertOk()
            ->assertSee('Attend / Register')
            ->assertDontSee('Registration is unavailable because this event is pending approval. Please wait until the event is confirmed.');

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.register', $event))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_registrations', [
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
        ]);

        $event->update(['status' => Event::STATUS_CANCELLED]);

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-in', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $scholar->id,
            'event_id' => $event->id,
        ]);

        $event->update([
            'status' => Event::STATUS_COMPLETED,
            'starts_at' => now()->subHours(4),
            'ends_at' => now()->subHour(),
        ]);

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-in', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $scholar->id,
            'event_id' => $event->id,
        ]);

        $freshEvent = Event::create([
            'title' => 'Completed Gate Event',
            'location' => 'Hall',
            'starts_at' => now()->subHours(4),
            'ends_at' => now()->subHour(),
            'service_hours' => 2,
            'status' => Event::STATUS_COMPLETED,
            'scholarship_program_id' => $event->scholarship_program_id,
        ]);

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'completed', 'event' => $freshEvent->id]))
            ->assertOk()
            ->assertDontSee('Attend / Register')
            ->assertSee('This event is completed and is no longer open for registration.');

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $freshEvent->id]))
            ->post(route('user.events.register', $freshEvent))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('event_registrations', [
            'user_id' => $scholar->id,
            'event_id' => $freshEvent->id,
        ]);
    }

    public function test_creating_an_event_starts_with_zero_registrations_and_keeps_attendance_closed(): void
    {
        [$staff, , $scholar] = $this->makeStaffWithConfirmedEvent([], true);

        $this->actingAs($staff)
            ->post(route('staff.events.store'), [
                'title' => 'Orientation Day',
                'location' => 'Gymnasium',
                'starts_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(3)->addHours(2)->format('Y-m-d H:i:s'),
                'service_hours' => 3,
            ])
            ->assertRedirect();

        $event = Event::query()->where('title', 'Orientation Day')->firstOrFail();

        $this->assertSame(Event::STATUS_PENDING, $event->status);
        $this->assertFalse($event->isAttendanceOpen());
        $this->assertDatabaseMissing('event_registrations', [
            'event_id' => $event->id,
        ]);
        $this->assertDatabaseMissing('attendances', [
            'event_id' => $event->id,
        ]);

        $pendingHtml = $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'pending', 'event' => $event->id]))
            ->assertOk()
            ->assertSee('Orientation Day')
            ->assertSee('Pending')
            ->assertSee('CLOSED')
            ->assertDontSee('>Attend / Register</button>', false)
            ->getContent();

        preg_match('/<div class="event-detail-flags">(.*?)<\\/div>/s', $pendingHtml, $pendingFlags);
        $this->assertSame(1, substr_count($pendingFlags[1] ?? '', 'class="badge pending">Pending</span>'));
        $this->assertStringContainsString('CLOSED', $pendingFlags[1] ?? '');
        $this->assertSame(0, substr_count($pendingHtml, 'class="badge confirmed">Confirmed</span>'));

        $this->actingAs($staff)
            ->get(route('staff.events', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Orientation Day')
            ->assertSee('Pending');

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CONFIRMED])
            ->assertRedirect(route('staff.events'))
            ->assertSessionHas('success');

        $this->assertSame(Event::STATUS_CONFIRMED, $event->fresh()->status);
        $this->assertFalse($event->fresh()->isAttendanceOpen());
        $this->assertDatabaseMissing('event_registrations', [
            'event_id' => $event->id,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.events.show', $event))
            ->assertOk()
            ->assertSee('<h2>Attend/Register</h2>', false)
            ->assertSee('No scholars have registered for this event yet.')
            ->assertDontSee($scholar->full_name);

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.register', $event))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.register', $event))
            ->assertRedirect();

        $this->assertSame(1, EventRegistration::query()->where('event_id', $event->id)->where('user_id', $scholar->id)->count());
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $scholar->id,
            'event_id' => $event->id,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.events.show', $event))
            ->assertOk()
            ->assertSee($scholar->full_name);

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('0 approved')
            ->assertSee('0 pending')
            ->assertSee('0 failed to check in')
            ->assertDontSee($scholar->full_name);

        $confirmedHtml = $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'confirmed', 'event' => $event->id]))
            ->assertOk()
            ->assertSee('Confirmed')
            ->assertSee('CLOSED')
            ->getContent();

        preg_match('/<div class="event-detail-flags">(.*?)<\\/div>/s', $confirmedHtml, $confirmedFlags);
        $this->assertSame(1, substr_count($confirmedFlags[1] ?? '', 'class="badge confirmed">Confirmed</span>'));
        $this->assertStringContainsString('CLOSED', $confirmedFlags[1] ?? '');

        $this->actingAs($staff)
            ->patch(route('staff.events.status', $event), ['status' => Event::STATUS_CANCELLED])
            ->assertRedirect(route('staff.events'));

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'cancelled', 'event' => $event->id]))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertDontSee('class="badge confirmed">Confirmed</span>', false);
    }

    public function test_scholar_page_load_auto_completes_confirmed_but_not_cancelled(): void
    {
        [, $confirmed, $scholar] = $this->makeStaffWithConfirmedEvent([
            'title' => 'Past Confirmed For Scholar',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ], true);

        // Allow past events on the list (activation before the event window).
        $scholar->forceFill(['events_visible_from' => now()->subYear()])->save();

        $cancelled = Event::create([
            'title' => 'Past Cancelled For Scholar',
            'location' => 'Hall',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
            'service_hours' => 2,
            'status' => Event::STATUS_CANCELLED,
            'scholarship_program_id' => $confirmed->scholarship_program_id,
        ]);

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'all']))
            ->assertOk();

        $this->assertSame(Event::STATUS_COMPLETED, $confirmed->fresh()->status);
        $this->assertSame(Event::STATUS_CANCELLED, $cancelled->fresh()->status);

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'completed', 'event' => $confirmed->id]))
            ->assertOk()
            ->assertSee('Past Confirmed For Scholar')
            ->assertSee('Completed');

        $this->actingAs($scholar)
            ->get(route('user.events', ['status' => 'cancelled', 'event' => $cancelled->id]))
            ->assertOk()
            ->assertSee('Past Cancelled For Scholar')
            ->assertSee('Cancelled');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Event, 2?: User}
     */
    private function makeStaffWithConfirmedEvent(array $overrides = [], bool $withScholar = false): array
    {
        $program = $this->makeProgram();
        $club = ScholarshipClub::createForProgram('Event Club', $program->id);

        $staff = User::register([
            'full_name' => 'Event Staff',
            'scholar_id' => 'STAFF-EVENT-001',
            'email' => 'event-staff-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $event = Event::create(array_merge([
            'title' => 'Community Cleanup',
            'location' => 'City Hall',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'service_hours' => 4,
            'status' => Event::STATUS_CONFIRMED,
            'scholarship_program_id' => $program->id,
        ], $overrides));

        if (! $withScholar) {
            return [$staff, $event];
        }

        $scholar = User::register([
            'full_name' => 'Event Scholar',
            'scholar_id' => 'SCH-EVENT-'.uniqid(),
            'email' => 'event-scholar-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        return [$staff, $event, $scholar];
    }

    private function makeProgram(): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-event-status-'.uniqid(),
            'is_active' => true,
        ]);
    }
}
