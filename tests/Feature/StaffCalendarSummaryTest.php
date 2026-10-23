<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffCalendarSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_staff_calendar_summarizes_distinct_events_by_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 01:00:00', 'Asia/Manila'));
        [$staff, $program] = $this->makeStaff();
        $other = $this->makeProgram('Other City');

        $funRun = $this->makeEvent($program, 'Fun Run 2k26', '2026-10-23 23:15:00', '2026-10-24 23:15:00', Event::STATUS_CONFIRMED);
        $this->makeEvent($program, 'Tree Planting', '2026-10-30 08:00:00', '2026-10-30 12:00:00', Event::STATUS_CONFIRMED);
        $this->makeEvent($program, 'Evening Meeting', '2026-10-30 13:00:00', '2026-10-30 15:00:00', Event::STATUS_PENDING);
        $this->makeEvent($program, 'November Outreach', '2026-11-05 09:00:00', '2026-11-05 11:00:00', Event::STATUS_CONFIRMED);
        $pending = $this->makeEvent($program, 'Pending Workshop', '2026-10-28 09:00:00', '2026-10-28 12:00:00', Event::STATUS_PENDING);
        $this->makeEvent($program, 'Cancelled Cleanup', '2026-10-22 09:00:00', '2026-10-22 12:00:00', Event::STATUS_CANCELLED);
        $this->makeEvent($program, 'Finished Assembly', '2026-10-20 09:00:00', '2026-10-21 17:00:00', Event::STATUS_COMPLETED);
        $this->makeEvent($other, 'Other City Drive', '2026-10-24 09:00:00', '2026-10-24 12:00:00', Event::STATUS_CONFIRMED);

        $hidden = $this->makeEvent($program, 'Old Hidden Event', '2026-09-02 09:00:00', '2026-09-02 12:00:00', Event::STATUS_CONFIRMED);
        $hidden->forceFill(['created_at' => Carbon::parse('2026-09-01 08:00:00', 'Asia/Manila')])->save();

        $scholars = [$this->makeScholar($program, 'One'), $this->makeScholar($program, 'Two')];
        foreach ($scholars as $scholar) {
            Attendance::create([
                'user_id' => $scholar->id,
                'event_id' => $funRun->id,
                'check_in' => Carbon::parse('2026-10-24 00:10:00', 'Asia/Manila'),
                'hours_earned' => 4,
                'status' => Attendance::STATUS_APPROVED,
            ]);
        }

        $page = $this->actingAs($staff)
            ->get(route('staff.calendar', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->assertSee('MY SCHEDULE SUMMARY')
            ->assertSee('Confirmed Events')
            ->assertSee('Pending Events')
            ->assertSee('Cancelled Events')
            ->assertSee('Completed Events')
            ->assertSee('Today')
            ->assertSee('+ Add Event')
            ->assertSee('Fun Run 2k26')
            ->assertSee('Until 11:15 PM')
            ->assertDontSee('Other City Drive')
            ->assertDontSee('Old Hidden Event')
            ->assertDontSee('Participated')
            ->assertDontSee('Not Joined');

        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'staff-cal-grid'), strpos($html, 'MY SCHEDULE SUMMARY'));
        $this->assertMatchesRegularExpression('/<strong>\s*3\s*<\/strong>\s*<span>Confirmed Events<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<strong>\s*2\s*<\/strong>\s*<span>Pending Events<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<strong>\s*1\s*<\/strong>\s*<span>Cancelled Events<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<strong>\s*1\s*<\/strong>\s*<span>Completed Events<\/span>/', $html);
        $this->assertSame(2, substr_count($html, 'Fun Run 2k26</span>'));
        $this->assertSame(2, preg_match_all('/class="staff-cal-event green"[^>]*>\s*<span class="staff-cal-event-title">Fun Run 2k26<\/span>/', $html));
        $this->assertSame(2, preg_match_all('/class="staff-cal-event gray"[^>]*>\s*<span class="staff-cal-event-title">Finished Assembly<\/span>/', $html));
        $this->assertSame(1, preg_match_all('/class="staff-cal-event red"[^>]*>\s*<span class="staff-cal-event-title">Cancelled Cleanup<\/span>/', $html));
        $this->assertSame(1, preg_match_all('/class="staff-cal-event orange"[^>]*>\s*<span class="staff-cal-event-title">Pending Workshop<\/span>/', $html));
        $this->assertMatchesRegularExpression('/class="staff-cal-event green"[^>]*>\s*<span class="staff-cal-event-title">Tree Planting<\/span>[\s\S]*?class="staff-cal-event orange"[^>]*>\s*<span class="staff-cal-event-title">Evening Meeting<\/span>/', $html);
        $this->assertStringContainsString('staff-cal-legend-dot green', $html);
        $this->assertStringContainsString('staff-cal-legend-dot orange', $html);
        $this->assertStringContainsString('staff-cal-legend-dot red', $html);
        $this->assertStringContainsString('staff-cal-legend-dot gray', $html);

        $pending->update(['status' => Event::STATUS_CONFIRMED]);

        $recolored = $this->actingAs($staff)
            ->get(route('staff.calendar', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->getContent();
        $this->assertSame(1, preg_match_all('/class="staff-cal-event green"[^>]*>\s*<span class="staff-cal-event-title">Pending Workshop<\/span>/', $recolored));
        $this->assertSame(0, preg_match_all('/class="staff-cal-event orange"[^>]*>\s*<span class="staff-cal-event-title">Pending Workshop<\/span>/', $recolored));

        $updated = $this->actingAs($staff)
            ->get(route('staff.calendar', ['year' => 2026, 'month' => 11]))
            ->assertOk()
            ->assertSee('November Outreach')
            ->getContent();

        $this->assertMatchesRegularExpression('/<strong>\s*4\s*<\/strong>\s*<span>Confirmed Events<\/span>/', $updated);
        $this->assertMatchesRegularExpression('/<strong>\s*1\s*<\/strong>\s*<span>Pending Events<\/span>/', $updated);
        $this->assertSame(2, Attendance::query()->where('event_id', $funRun->id)->count());
    }

    /**
     * @return array{0: User, 1: ScholarshipProgram}
     */
    private function makeStaff(): array
    {
        $program = $this->makeProgram('Surigao City');
        $club = ScholarshipClub::createForProgram('Staff Calendar Club', $program->id);

        $staff = User::register([
            'full_name' => 'Calendar Staff',
            'scholar_id' => 'STAFF-CAL-'.uniqid(),
            'email' => 'staff-cal-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
        $staff->forceFill([
            'events_visible_from' => Carbon::parse('2026-10-01 00:00:00', 'Asia/Manila'),
        ])->save();

        return [$staff->fresh(), $program];
    }

    private function makeProgram(string $city): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => $city,
            'location_type' => 'city_municipality',
            'name' => $city.' Scholarship Program',
            'display_name' => $city,
            'province_name' => 'Surigao del Norte',
            'slug' => str($city)->slug().'-staff-cal-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function makeScholar(ScholarshipProgram $program, string $name): User
    {
        return User::register([
            'full_name' => 'Scholar '.$name,
            'scholar_id' => 'SCH-CAL-'.uniqid(),
            'email' => 'scholar-cal-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
    }

    private function makeEvent(ScholarshipProgram $program, string $title, string $start, string $end, string $status): Event
    {
        return Event::create([
            'title' => $title,
            'location' => 'City Hall',
            'starts_at' => Carbon::parse($start, 'Asia/Manila'),
            'ends_at' => Carbon::parse($end, 'Asia/Manila'),
            'service_hours' => 8,
            'status' => $status,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);
    }
}
