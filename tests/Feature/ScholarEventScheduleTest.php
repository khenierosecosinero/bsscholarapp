<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\ScholarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScholarEventScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_appear_only_on_the_dates_covered_by_their_saved_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 17:00:00', 'Asia/Manila'));

        $program = $this->makeProgram();
        $scholar = $this->makeScholar($program);
        $scholar->forceFill(['events_visible_from' => now()->subMonth()])->save();

        $spanning = Event::create([
            'title' => 'Overnight Assembly',
            'location' => 'City Hall',
            'starts_at' => now()->setTime(16, 57),
            'ends_at' => now()->addDay()->setTime(0, 56),
            'service_hours' => 2,
            'status' => Event::STATUS_PENDING,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);

        $sameDay = Event::create([
            'title' => 'Morning Cleanup',
            'location' => 'Park',
            'starts_at' => now()->addDays(3)->setTime(8, 0),
            'ends_at' => now()->addDays(3)->setTime(12, 0),
            'service_hours' => 4,
            'status' => Event::STATUS_CONFIRMED,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);

        $ended = Event::create([
            'title' => 'Finished Workshop',
            'location' => 'Library',
            'starts_at' => now()->subDay()->setTime(8, 0),
            'ends_at' => now()->subDay()->setTime(17, 0),
            'service_hours' => 3,
            'status' => Event::STATUS_CONFIRMED,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);

        $later = Event::create([
            'title' => 'November Outreach',
            'location' => 'Gym',
            'starts_at' => now()->addDays(20)->setTime(9, 0),
            'ends_at' => now()->addDays(20)->setTime(11, 0),
            'service_hours' => 2,
            'status' => Event::STATUS_CONFIRMED,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);

        $this->assertSame(
            [now()->toDateString(), now()->addDay()->toDateString()],
            $spanning->calendarDateKeys()
        );
        $this->assertSame([now()->addDays(3)->toDateString()], $sameDay->calendarDateKeys());
        $this->assertSame([now()->subDay()->toDateString()], $ended->calendarDateKeys());

        $midnight = Event::create([
            'title' => 'Ends At Midnight',
            'location' => 'Hall',
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'ends_at' => now()->addDays(3)->startOfDay(),
            'service_hours' => 1,
            'status' => Event::STATUS_PENDING,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);
        $this->assertSame([now()->addDays(2)->toDateString()], $midnight->calendarDateKeys());

        $service = app(ScholarService::class);
        $this->assertSame(3, $service->dashboardStats($scholar->fresh())['upcoming_events']);

        $upcomingTitles = $service->upcomingEvents($scholar->fresh(), 10)->pluck('title');
        $this->assertTrue($upcomingTitles->contains('Overnight Assembly'));
        $this->assertTrue($upcomingTitles->contains('Morning Cleanup'));
        $this->assertTrue($upcomingTitles->contains('November Outreach'));
        $this->assertFalse($upcomingTitles->contains('Finished Workshop'));
        $this->assertSame('Overnight Assembly', $service->nextUpcomingEvent($scholar->fresh())['title']);

        $this->actingAs($scholar)
            ->get(route('user.events'))
            ->assertOk()
            ->assertSee('Overnight Assembly')
            ->assertSee('October 21, 2026')
            ->assertSee('12:56 AM')
            ->assertSee('Morning Cleanup')
            ->assertDontSee('Finished Workshop');

        $october = $service->calendarEvents($scholar->fresh(), 2026, 10)->pluck('title');
        $this->assertTrue($october->contains('Overnight Assembly'));
        $this->assertTrue($october->contains('Finished Workshop'));
        $this->assertTrue($october->contains('Morning Cleanup'));
        $this->assertFalse($october->contains('November Outreach'));

        $november = $service->calendarEvents($scholar->fresh(), 2026, 11)->pluck('title');
        $this->assertEquals(['November Outreach'], $november->all());

        $this->actingAs($scholar)
            ->get(route('user.calendar', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->assertSee('Until 12:56 AM')
            ->assertSee('Finished Workshop')
            ->assertSee('8:00 AM - 12:00 PM');
    }

    private function makeProgram(): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'surigao-del-norte-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function makeScholar(ScholarshipProgram $program): User
    {
        return User::register([
            'full_name' => 'Schedule Scholar',
            'scholar_id' => 'SCH-'.uniqid(),
            'email' => 'scholar-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'province' => 'Surigao del Norte',
        ]);
    }
}
