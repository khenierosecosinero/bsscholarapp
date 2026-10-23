<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceDaySession;
use App\Models\AttendanceSessionLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AttendanceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventDateRangeAndSessionTimestampsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_end_date_cannot_be_earlier_than_the_start_date(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)
            ->from(route('staff.events.create'))
            ->post(route('staff.events.store'), [
                'title' => 'Too Early',
                'location' => 'City Hall',
                'starts_at' => '2026-10-25T09:00',
                'ends_at' => '2026-10-23T17:00',
                'service_hours' => 4,
            ])
            ->assertRedirect(route('staff.events.create'))
            ->assertSessionHasErrors('ends_at');

        $this->assertDatabaseMissing('events', ['title' => 'Too Early']);
    }

    public function test_multi_day_event_shows_its_date_range_and_keeps_each_days_attendance_times(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 15:00:00', 'Asia/Manila'));

        [$staff, $scholar, $event] = $this->makeMultiDayEvent();

        $this->assertSame('October 23, 2026 – October 25, 2026', $event->dateRangeLabel());
        $this->assertSame(
            ['2026-10-23', '2026-10-24', '2026-10-25'],
            $event->calendarDateKeys()
        );

        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('October 23, 2026')
            ->assertSee('October 25, 2026')
            ->assertSee('Open Attendance')
            ->assertSee('Close Attendance')
            ->assertDontSee('Attendance Status')
            ->assertDontSee('Opened At')
            ->assertDontSee('Closed At')
            ->assertDontSee('Last Opened At')
            ->assertDontSee('Session history')
            ->assertDontSee('Not yet opened')
            ->assertDontSee('Not yet closed');

        $this->actingAs($scholar)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('October 23, 2026 – October 25, 2026')
            ->assertDontSee('class="badge attendance-open"', false);

        $sessions = app(AttendanceSessionService::class);
        $sessions->open($event, $staff, '2026-10-23');

        $event->refresh();
        $this->assertTrue($event->isAttendanceOpen());
        $this->assertSame('2026-10-23 15:00:00', $event->attendance_opened_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s'));
        $this->assertNotSame($event->starts_at->format('Y-m-d H:i:s'), $event->attendance_opened_at?->format('Y-m-d H:i:s'));
        $this->assertSame(0, AttendanceDaySession::query()->where('event_id', $event->id)->count());

        $this->expectException(\InvalidArgumentException::class);
        try {
            $sessions->open($event->fresh(), $staff, '2026-10-24');
        } finally {
            $this->assertSame(0, AttendanceDaySession::query()->where('event_id', $event->id)->count());
        }
    }

    public function test_one_session_stays_closed_until_staff_opens_it_again(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 15:00:00', 'Asia/Manila'));
        [$staff, $scholar, $event] = $this->makeMultiDayEvent();
        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);

        $sessions = app(AttendanceSessionService::class);
        $sessions->open($event, $staff);
        Carbon::setTestNow(Carbon::parse('2026-10-23 15:45:00', 'Asia/Manila'));
        $sessions->close($event->fresh(), $staff);

        $event->refresh();
        $this->assertFalse($event->isAttendanceOpen());
        $this->assertSame('2026-10-23 15:45:00', $event->attendance_closed_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s'));
        $this->assertTrue($event->attendanceMayBeFinalized());

        Carbon::setTestNow(Carbon::parse('2026-10-24 09:00:00', 'Asia/Manila'));
        $event->refresh();
        $this->assertFalse($event->isAttendanceOpen());
        $this->assertSame(0, AttendanceDaySession::query()->where('event_id', $event->id)->count());

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-in', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $sessions->open($event->fresh(), $staff);
        $this->assertTrue($event->fresh()->isAttendanceOpen());

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-in', $event))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-in', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Attendance::query()->where('user_id', $scholar->id)->where('event_id', $event->id)->count());
        $this->assertSame(2, AttendanceSessionLog::query()
            ->where('event_id', $event->id)
            ->where('action', AttendanceSessionLog::ACTION_OPENED)
            ->count());
        $this->assertSame(1, AttendanceSessionLog::query()
            ->where('event_id', $event->id)
            ->where('action', AttendanceSessionLog::ACTION_CLOSED)
            ->count());

        $page = $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('October 23, 2026, 9:00 AM – October 25, 2026, 5:00 PM')
            ->assertDontSee('name="session_date"', false)
            ->assertDontSee('Session history');

        $this->assertSame(1, substr_count($page->getContent(), '>Open Attendance</button>'));
        $this->assertSame(1, substr_count($page->getContent(), '>Close Attendance</button>'));
    }

    public function test_scholars_cannot_submit_outside_the_event_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 15:00:00', 'Asia/Manila'));
        [$staff, $scholar, $event] = $this->makeMultiDayEvent();
        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);

        app(AttendanceSessionService::class)->open($event, $staff);
        $this->assertTrue($event->fresh()->isAttendanceOpen());

        $this->actingAs($scholar)
            ->from(route('user.events', ['event' => $event->id]))
            ->post(route('user.events.check-in', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'check_in' => now(),
        ]);
        $this->assertNull(Attendance::query()->where('user_id', $scholar->id)->where('event_id', $event->id)->value('check_in'));
    }

    public function test_a_closed_event_session_covers_every_remaining_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 23:43:00', 'Asia/Manila'));
        [$staff, $scholar, $event] = $this->makeMultiDayEvent();

        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);

        $opened = Carbon::parse('2026-10-23 23:15:16', 'Asia/Manila');
        $closed = Carbon::parse('2026-10-23 23:15:43', 'Asia/Manila');
        $event->update([
            'attendance_is_open' => false,
            'attendance_opened_at' => $opened,
            'attendance_closed_at' => $closed,
            'attendance_opened_by' => $staff->id,
            'attendance_closed_by' => $staff->id,
        ]);

        $page = $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id]))
            ->assertOk()
            ->assertDontSee('name="session_date"', false);

        $this->assertSame(1, substr_count($page->getContent(), '>Open Attendance</button>'));
        $this->assertSame(1, substr_count($page->getContent(), '>Close Attendance</button>'));

        $this->actingAs($scholar)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('class="badge attendance-open"', false);

        $this->assertSame(
            Attendance::STATUS_FAILED_CHECK_IN,
            Attendance::query()->where('user_id', $scholar->id)->where('event_id', $event->id)->value('status')
        );
        $this->assertEquals(0, (float) Attendance::query()->where('user_id', $scholar->id)->where('event_id', $event->id)->value('hours_earned'));
    }

    public function test_staff_can_edit_event_dates_into_a_multi_day_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 15:00:00', 'Asia/Manila'));
        [$staff, , $event] = $this->makeMultiDayEvent();

        $this->actingAs($staff)
            ->from(route('staff.events.show', $event))
            ->patch(route('staff.events.schedule', $event), [
                'starts_at' => '2026-10-23T09:00',
                'ends_at' => '2026-10-23T08:00',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('ends_at');

        $this->actingAs($staff)
            ->patch(route('staff.events.schedule', $event), [
                'starts_at' => '2026-10-23T09:00',
                'ends_at' => '2026-10-26T17:00',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $event->refresh();
        $this->assertSame('October 23, 2026 – October 26, 2026', $event->dateRangeLabel());
    }

    /**
     * @return array{0: User, 1: User, 2: Event}
     */
    private function makeMultiDayEvent(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-date-range-'.uniqid(),
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Date Range Club', $program->id);

        $staff = User::register([
            'full_name' => 'Date Staff',
            'scholar_id' => 'STAFF-DATE-'.uniqid(),
            'email' => 'date-staff-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $scholar = User::register([
            'full_name' => 'Date Scholar',
            'scholar_id' => 'SCH-DATE-'.uniqid(),
            'email' => 'date-scholar-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $event = Event::create([
            'title' => 'Coastal Cleanup',
            'location' => 'Boulevard',
            'starts_at' => Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'),
            'ends_at' => Carbon::parse('2026-10-25 17:00:00', 'Asia/Manila'),
            'service_hours' => 20,
            'status' => Event::STATUS_CONFIRMED,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);

        return [$staff, $scholar, $event];
    }

    private function makeStaff(): User
    {
        [$staff] = $this->makeMultiDayEvent();

        return $staff;
    }
};
