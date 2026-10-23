<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScholarCalendarActiveSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_completed_and_cancelled_events_stay_on_their_dates_with_event_status_counts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 00:30:00', 'Asia/Manila'));
        [$scholar, $program] = $this->makeScholar();

        $completed = $this->makeEvent($program, 'Finished Assembly', '2026-10-20 09:00:00', '2026-10-21 17:00:00', Event::STATUS_COMPLETED);
        $cancelled = $this->makeEvent($program, 'Cancelled Cleanup', '2026-10-22 09:00:00', '2026-10-22 12:00:00', Event::STATUS_CANCELLED);
        $next = $this->makeEvent($program, 'Fun Run 2k26', '2026-10-23 23:15:00', '2026-10-24 23:15:00', Event::STATUS_CONFIRMED);
        $later = $this->makeEvent($program, 'Tree Planting', '2026-10-30 08:00:00', '2026-10-30 12:00:00', Event::STATUS_CONFIRMED);

        $period = app(AcademicSettingsService::class)->selectedPeriod();
        Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $completed->id,
            'academic_year_start' => $period->year_start,
            'academic_year_end' => $period->year_end,
            'semester' => $period->semester,
            'check_in' => Carbon::parse('2026-10-20 09:05:00', 'Asia/Manila'),
            'check_out' => Carbon::parse('2026-10-20 12:00:00', 'Asia/Manila'),
            'hours_earned' => 8,
            'status' => Attendance::STATUS_APPROVED,
        ]);

        $page = $this->actingAs($scholar)
            ->get(route('user.calendar', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->assertSee('<h3>Fun Run 2k26</h3>', false)
            ->assertSee('Fun Run 2k26')
            ->assertSee('Tree Planting')
            ->assertSee('Finished Assembly')
            ->assertSee('Cancelled Cleanup')
            ->assertSee('October 23, 2026 – October 24, 2026')
            ->assertSee('11:15 PM – Oct 24, 2026 11:15 PM')
            ->assertSee('Confirmed Events')
            ->assertSee('Pending Events')
            ->assertSee('Cancelled Events')
            ->assertSee('Completed Events')
            ->assertDontSee('Participated')
            ->assertDontSee('Not Joined')
            ->assertDontSee('legend-dot blue')
            ->assertDontSee('legend-dot purple');

        $html = $page->getContent();
        $this->assertMatchesRegularExpression('/<strong>\s*2\s*<\/strong>\s*<span>Confirmed Events<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<strong>\s*0\s*<\/strong>\s*<span>Pending Events<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<strong>\s*1\s*<\/strong>\s*<span>Cancelled Events<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<strong>\s*1\s*<\/strong>\s*<span>Completed Events<\/span>/', $html);
        $this->assertSame(2, substr_count($html, 'Finished Assembly</span>'));
        $this->assertStringContainsString('fc-event gray', $html);
        $this->assertStringContainsString('fc-event red', $html);
        $this->assertStringContainsString('fc-event green', $html);

        $this->actingAs($scholar)
            ->get(route('user.calendar', ['year' => 2026, 'month' => 10, 'event' => $completed->id]))
            ->assertOk()
            ->assertSee('<h3>Finished Assembly</h3>', false)
            ->assertSee('Completed');

        $this->actingAs($scholar)
            ->get(route('user.calendar', ['year' => 2026, 'month' => 10, 'event' => $cancelled->id]))
            ->assertOk()
            ->assertSee('<h3>Cancelled Cleanup</h3>', false)
            ->assertSee('Cancelled');

        $this->actingAs($scholar)
            ->getJson(route('user.attendance.status', [
                'calendar' => 1,
                'year' => 2026,
                'month' => 10,
                'event' => $next->id,
            ]))
            ->assertOk()
            ->assertJsonPath('calendar_selection.requested_eligible', true)
            ->assertJsonPath('calendar_selection.selected_id', $next->id);

        $next->update(['status' => Event::STATUS_COMPLETED]);

        $completedPage = $this->actingAs($scholar)
            ->get(route('user.calendar', ['year' => 2026, 'month' => 11]))
            ->assertOk()
            ->assertSee('No upcoming events available.')
            ->assertDontSee('Fun Run 2k26');

        $this->assertMatchesRegularExpression('/<strong>\s*1\s*<\/strong>\s*<span>Confirmed Events<\/span>/', $completedPage->getContent());
        $this->assertMatchesRegularExpression('/<strong>\s*2\s*<\/strong>\s*<span>Completed Events<\/span>/', $completedPage->getContent());

        $this->actingAs($scholar)
            ->get(route('user.service-hours'))
            ->assertOk()
            ->assertSee('Finished Assembly')
            ->assertSee('8.00 hrs');

        $this->assertSame(Attendance::STATUS_APPROVED, Attendance::query()->where('event_id', $completed->id)->value('status'));
        $this->assertEquals(8, (float) Attendance::query()->where('event_id', $completed->id)->value('hours_earned'));
    }

    public function test_an_event_stays_visible_on_each_scheduled_day_after_its_start_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 00:30:00', 'Asia/Manila'));
        [$scholar, $program] = $this->makeScholar();

        $this->makeEvent($program, 'Earlier Pending', '2026-10-22 21:33:00', '2026-10-22 23:59:00', Event::STATUS_PENDING);
        $this->makeEvent($program, 'Fun Run 2k26', '2026-10-23 23:15:00', '2026-10-24 23:15:00', Event::STATUS_CONFIRMED);
        $this->makeEvent($program, 'Morning Drive', '2026-10-24 00:10:00', '2026-10-24 18:00:00', Event::STATUS_CONFIRMED);
        $this->makeEvent($program, 'Cancelled Cleanup', '2026-10-24 09:00:00', '2026-10-24 12:00:00', Event::STATUS_CANCELLED);

        $page = $this->actingAs($scholar)
            ->get(route('user.calendar', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->assertSee('<h3>Fun Run 2k26</h3>', false)
            ->assertSee('Earlier Pending')
            ->assertSee('Morning Drive')
            ->assertSee('11:15 PM')
            ->assertSee('Until 11:15 PM')
            ->assertSee('Cancelled Cleanup')
            ->assertSee('fc-event red')
            ->assertSee('fc-event orange')
            ->assertDontSee('Participated')
            ->assertDontSee('Not Joined');

        $html = $page->getContent();
        $this->assertSame(2, substr_count($html, 'Fun Run 2k26</span>'));
    }

    public function test_participation_does_not_hide_an_event_that_is_still_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 10:00:00', 'Asia/Manila'));
        [$scholar, $program] = $this->makeScholar();
        $event = $this->makeEvent($program, 'Ongoing Drive', '2026-10-24 08:00:00', '2026-10-25 17:00:00', Event::STATUS_CONFIRMED);

        $period = app(AcademicSettingsService::class)->selectedPeriod();
        Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'academic_year_start' => $period->year_start,
            'academic_year_end' => $period->year_end,
            'semester' => $period->semester,
            'check_in' => now()->subHour(),
            'check_out' => now()->subMinutes(10),
            'hours_earned' => 4,
            'status' => Attendance::STATUS_APPROVED,
        ]);

        $this->actingAs($scholar)
            ->get(route('user.calendar', ['year' => 2026, 'month' => 10, 'event' => $event->id]))
            ->assertOk()
            ->assertSee('Ongoing Drive')
            ->assertSee('Participation Confirmed')
            ->assertSee('fc-event green')
            ->assertDontSee('Participated')
            ->assertDontSee('Not Joined');

        $summary = $this->actingAs($scholar)
            ->get(route('user.calendar', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/<strong>\s*1\s*<\/strong>\s*<span>Confirmed Events<\/span>/', $summary);
        $this->assertMatchesRegularExpression('/<strong>\s*0\s*<\/strong>\s*<span>Completed Events<\/span>/', $summary);
    }

    /**
     * @return array{0: User, 1: ScholarshipProgram}
     */
    private function makeScholar(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-calendar-'.uniqid(),
            'is_active' => true,
        ]);

        $scholar = User::register([
            'full_name' => 'Calendar Scholar',
            'scholar_id' => 'SCH-CAL-'.uniqid(),
            'email' => 'calendar-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
        $scholar->forceFill(['events_visible_from' => now()->subYear()])->save();

        return [$scholar, $program];
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
