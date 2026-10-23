<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AttendanceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarAttendanceStatusDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_events_show_closed_until_staff_opens_attendance(): void
    {
        [$staff, $event, $scholar] = $this->makeHappeningEvent();

        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);

        $this->actingAs($scholar)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Community Cleanup')
            ->assertSee('attendance-closed', false)
            ->assertDontSee('Attendance for Community Cleanup on '.$event->starts_at->format('M j, Y').' at '.$event->starts_at->format('g:i A').' is OPEN until');

        $dashboard = $this->actingAs($scholar)->get(route('user.dashboard'));
        $this->assertSame(0, substr_count($dashboard->getContent(), 'class="badge attendance-open"'));
        $this->assertGreaterThan(0, substr_count($dashboard->getContent(), '>CLOSED</span>'));

        $events = $this->actingAs($scholar)
            ->get(route('user.events', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('Community Cleanup')
            ->assertSee('Attendance CLOSED')
            ->assertSee('Attendance for Community Cleanup is CLOSED. You can mark attendance only after Scholar Staff opens the session.')
            ->assertSee('disabled', false);

        $this->assertGreaterThan(0, substr_count($events->getContent(), '>CLOSED</span>'));
        $this->assertSame(0, substr_count($events->getContent(), 'class="badge attendance-open"'));

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-in', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $scholar->id,
            'event_id' => $event->id,
        ]);

        $live = $this->actingAs($scholar)
            ->getJson(route('user.attendance.status', ['event' => $event->id]))
            ->assertOk()
            ->json();

        $this->assertSame('CLOSED', $live['events'][(string) $event->id]['status']);
        $this->assertFalse($live['events'][(string) $event->id]['is_open']);
        $this->assertFalse($live['events'][(string) $event->id]['schedule_open']);
        $this->assertSame('CLOSED', $live['events'][(string) $event->id]['schedule_status']);
        $this->assertStringNotContainsString('OPEN until', $live['events'][(string) $event->id]['message']);
        $this->assertStringNotContainsString('OPEN until', $live['events'][(string) $event->id]['schedule_message']);
        $this->assertFalse($live['events'][(string) $event->id]['can_check_in']);

        app(AttendanceSessionService::class)->open($event, $staff);
        $event->refresh();

        $this->actingAs($scholar)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('>OPEN</span>', false)
            ->assertSee('attendance-open', false);

        $this->actingAs($scholar)
            ->get(route('user.events', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('Attendance OPEN')
            ->assertSee('Attendance is OPEN. You can check in now.')
            ->assertDontSee('disabled>', false);

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-in', $event))
            ->assertRedirect()
            ->assertSessionHas('success');

        app(AttendanceSessionService::class)->close($event->fresh(), $staff);

        $this->actingAs($scholar)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('>CLOSED</span>', false)
            ->assertDontSee('class="badge attendance-open"', false);

        $this->actingAs($scholar)
            ->get(route('user.events', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('Attendance CLOSED')
            ->assertSee('Attendance is CLOSED.')
            ->assertDontSee('You can no longer submit or modify your attendance.');

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-out', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull(Attendance::query()->where('user_id', $scholar->id)->where('event_id', $event->id)->value('check_out'));
    }

    public function test_staff_attendance_page_lists_a_registered_scholar_as_failed_after_close(): void
    {
        [$staff, $event, $scholar] = $this->makeHappeningEvent();

        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);

        $sessions = app(AttendanceSessionService::class);
        $sessions->open($event, $staff);
        $sessions->close($event->fresh(), $staff);

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('CLOSED')
            ->assertSee('1 failed to check in')
            ->assertSee($scholar->full_name)
            ->assertDontSee('>OPEN</span>', false);

        $attendance = Attendance::query()
            ->where('user_id', $scholar->id)
            ->where('event_id', $event->id)
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame(Attendance::STATUS_FAILED_CHECK_IN, $attendance->status);
        $this->assertEquals(0, (float) $attendance->hours_earned);
        $this->assertNull($attendance->check_in);
    }

    /**
     * @return array{0: User, 1: Event, 2: User}
     */
    private function makeHappeningEvent(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-attendance-status-'.uniqid(),
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Attendance Club', $program->id);

        $staff = User::register([
            'full_name' => 'Attendance Staff',
            'scholar_id' => 'STAFF-ATT-STATUS',
            'email' => 'attendance-status-staff-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $scholar = User::register([
            'full_name' => 'Attendance Scholar',
            'scholar_id' => 'SCH-ATT-STATUS-'.uniqid(),
            'email' => 'attendance-status-scholar-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $start = now()->copy()->subMinutes(30);
        $end = now()->copy()->addMinutes(30);
        if (! $start->isSameDay($end)) {
            $start = now()->copy()->startOfDay();
            $end = now()->copy()->endOfDay();
        }

        $event = Event::create([
            'title' => 'Community Cleanup',
            'location' => 'City Hall',
            'starts_at' => $start,
            'ends_at' => $end,
            'service_hours' => 4,
            'status' => Event::STATUS_CONFIRMED,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);

        return [$staff, $event, $scholar];
    }
}
