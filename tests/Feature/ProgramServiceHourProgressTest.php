<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Services\ScholarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramServiceHourProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_year_progress_follows_each_scholars_start_and_the_administrator_year(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $academic->updateGlobal(['year_start' => 2024, 'semester' => '1st Semester'], $admin);

        [$program, $club] = $this->makeProgram();
        $early = $this->makeScholar($program, $club, 'Early Scholar', 'early-progress@example.com', 'SCH-EARLY', '1st Year');

        $this->assertSame(2024, $early->program_start_year);

        $approved = $this->makeAttendance($program, $early, 2024, '1st Semester', 10, Attendance::STATUS_APPROVED);
        $pending = $this->makeAttendance($program, $early, 2024, '2nd Semester', 4, Attendance::STATUS_PENDING);
        $outside = $this->makeAttendance($program, $early, 2020, '1st Semester', 30, Attendance::STATUS_APPROVED);

        $tracking = app(ScholarService::class)->programHourTracking($early->fresh());

        $this->assertSame(2024, $tracking['start_year']);
        $this->assertSame([2024, 2025, 2026, 2027], array_column($tracking['years'], 'year_start'));
        $this->assertSame(10.0, $this->semester($tracking, 2024, '1st Semester')['approved']);
        $this->assertSame(20.0, $this->semester($tracking, 2024, '1st Semester')['remaining']);
        $this->assertSame(0.0, $this->semester($tracking, 2024, '1st Semester')['pending']);
        $this->assertSame(4.0, $this->semester($tracking, 2024, '2nd Semester')['pending']);
        $this->assertSame(0.0, $this->semester($tracking, 2024, '2nd Semester')['approved']);
        $this->assertSame(30.0, $this->semester($tracking, 2024, '2nd Semester')['remaining']);
        $this->assertTrue($this->semester($tracking, 2024, '1st Semester')['is_current']);
        $this->assertSame(2020, $outside->fresh()->academic_year_start);

        $academic->updateGlobal(['year_start' => 2026, 'semester' => '2nd Semester'], $admin);

        $early->refresh();
        $this->assertSame(2024, $early->program_start_year);
        $this->assertSame(2024, $approved->fresh()->academic_year_start);
        $this->assertSame('1st Semester', $approved->fresh()->semester);
        $this->assertSame(2024, $pending->fresh()->academic_year_start);
        $this->assertSame(2020, $outside->fresh()->academic_year_start);

        $tracking = app(ScholarService::class)->programHourTracking($early);
        $this->assertSame([2024, 2025, 2026, 2027], array_column($tracking['years'], 'year_start'));
        $this->assertSame(10.0, $this->semester($tracking, 2024, '1st Semester')['approved']);
        $this->assertFalse($this->semester($tracking, 2024, '1st Semester')['is_current']);
        $this->assertTrue($this->semester($tracking, 2026, '2nd Semester')['is_current']);
        $this->assertSame(2026, $academic->forUser($early)->year_start);
        $this->assertSame('2nd Semester', $academic->forUser($early)->semester);
        $this->assertSame(0.0, app(ScholarService::class)->serviceHourStats($early)['approved']);

        $fourth = $this->makeScholar($program, $club, 'Fourth Scholar', 'fourth-progress@example.com', 'SCH-FOURTH', '4th Year');
        $this->assertSame(2023, $fourth->program_start_year);
        $this->makeAttendance($program, $fourth, 2026, '2nd Semester', 27, Attendance::STATUS_APPROVED);
        $this->makeAttendance($program, $fourth, 2026, '2nd Semester', 5, Attendance::STATUS_PENDING);

        $fourthTracking = app(ScholarService::class)->programHourTracking($fourth->fresh());
        $this->assertSame([2023, 2024, 2025, 2026], array_column($fourthTracking['years'], 'year_start'));
        $this->assertSame(27.0, $this->semester($fourthTracking, 2026, '2nd Semester')['approved']);
        $this->assertSame(5.0, $this->semester($fourthTracking, 2026, '2nd Semester')['pending']);
        $this->assertSame(3.0, $this->semester($fourthTracking, 2026, '2nd Semester')['remaining']);
        $this->assertSame(0.0, $this->semester($fourthTracking, 2023, '1st Semester')['approved']);

        $staff = User::register([
            'full_name' => 'Progress Staff',
            'scholar_id' => 'STAFF-PROGRESS',
            'email' => 'progress-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.scholars.show', $early))
            ->assertOk()
            ->assertSee('Year 1')
            ->assertSee('Year 4')
            ->assertSee('AY 2024')
            ->assertDontSee('SCH-EARLY 2024')
            ->assertDontSee('SCH-EARLY 2020');

        $this->actingAs($staff)
            ->get(route('staff.scholars.show', $fourth))
            ->assertOk()
            ->assertSee('4-Year Service Hour Progress')
            ->assertSee('Year 1')
            ->assertSee('Year 2')
            ->assertSee('Year 3')
            ->assertSee('Year 4')
            ->assertSee('AY 2023')
            ->assertSee('27.00')
            ->assertSee('5.00')
            ->assertSee('3.00');

        $this->actingAs($fourth)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('AY 2026–2027')
            ->assertSee('2nd Semester')
            ->assertSee('27');

        $this->actingAs($early)
            ->get(route('user.service-hours'))
            ->assertOk()
            ->assertSee('0.00 hours');

        $this->actingAs($staff)
            ->from(route('staff.settings'))
            ->put(route('academic.period.update'), [
                'year_start' => 2024,
                'semester' => '1st Semester',
            ])
            ->assertRedirect(route('staff.settings'));

        $this->actingAs($staff)
            ->get(route('staff.reports.service-hours'))
            ->assertOk()
            ->assertSee('AY 2024');

        $fourth->forceFill([
            'academic_year_start' => 2019,
            'semester' => '1st Semester',
        ])->save();

        $this->assertSame(2026, $academic->forUser($fourth->fresh())->year_start);
        $this->assertSame(2023, $fourth->fresh()->program_start_year);

        $this->actingAs($fourth)
            ->put(route('user.profile.update'), [
                'course_year_level' => 'Bachelor of Science in Information Technology',
                'year_level' => '2nd Year',
                'city' => 'Dapa',
                'province' => 'Surigao del Norte',
                'cellphone_number' => '09171234567',
            ])
            ->assertRedirect();

        $fourth->refresh();
        $this->assertSame('2nd Year', $fourth->year_level);
        $this->assertSame(2023, $fourth->program_start_year);
        $this->assertSame(2026, Attendance::query()->where('user_id', $fourth->id)->value('academic_year_start'));

        $this->actingAs($staff)
            ->put(route('academic.period.update'), [
                'year_start' => 2024,
                'semester' => '1st Semester',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2024, $approved->fresh()->academic_year_start);
        $this->assertSame(2026, $academic->current()->year_start);

        $filtered = app(ScholarService::class)->programHourTracking($early->fresh());
        $this->assertSame([2024, 2025, 2026, 2027], array_column($filtered['years'], 'year_start'));
        $this->assertSame(10.0, $this->semester($filtered, 2024, '1st Semester')['approved']);
        $this->assertTrue($this->semester($filtered, 2024, '1st Semester')['is_current']);

        $this->actingAs($staff)
            ->get(route('staff.scholars'))
            ->assertOk()
            ->assertSee('Early Scholar')
            ->assertSee('Fourth Scholar')
            ->assertDontSee('No scholars in Year')
            ->assertDontSee('Start Academic Year not set')
            ->assertDontSee('No scholars need a Start Academic Year.');
    }

    public function test_surplus_approved_hours_carry_into_the_next_semester_without_rewriting_records(): void
    {
        $admin = $this->makeAdmin();
        app(AcademicSettingsService::class)->updateGlobal([
            'year_start' => 2026,
            'semester' => '2nd Semester',
        ], $admin);

        [$program, $club] = $this->makeProgram();
        $scholar = $this->makeScholar($program, $club, 'Carry Scholar', 'carry-progress@example.com', 'SCH-CARRY', '1st Year');
        $record = $this->makeAttendance($program, $scholar, 2026, '1st Semester', 40, Attendance::STATUS_APPROVED);

        $tracking = app(ScholarService::class)->programHourTracking($scholar->fresh());

        $this->assertSame(30.0, $this->semester($tracking, 2026, '1st Semester')['approved']);
        $this->assertSame(0.0, $this->semester($tracking, 2026, '1st Semester')['remaining']);
        $this->assertSame(10.0, $this->semester($tracking, 2026, '2nd Semester')['approved']);
        $this->assertSame(20.0, $this->semester($tracking, 2026, '2nd Semester')['remaining']);
        $this->assertSame(1, Attendance::query()->where('user_id', $scholar->id)->count());
        $this->assertSame('40.00', $record->fresh()->hours_earned);
        $this->assertSame(2026, $record->fresh()->academic_year_start);
    }

    public function test_start_academic_year_drives_four_year_map_and_is_not_overwritten_by_filters(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $academic->updateGlobal(['year_start' => 2022, 'semester' => '1st Semester'], $admin);
        $academic->updateGlobal(['year_start' => 2023, 'semester' => '1st Semester'], $admin);
        $academic->updateGlobal(['year_start' => 2024, 'semester' => '1st Semester'], $admin);
        $academic->updateGlobal(['year_start' => 2025, 'semester' => '1st Semester'], $admin);
        $academic->updateGlobal(['year_start' => 2026, 'semester' => '2nd Semester'], $admin);

        [$program, $club] = $this->makeProgram();
        $scholar = $this->makeScholar($program, $club, 'Start Year Scholar', 'start-year@example.com', 'SCH-START', '1st Year', null);

        $this->assertNull($scholar->program_start_year);
        $this->assertTrue(app(ScholarService::class)->programHourTracking($scholar)['needs_start_year']);

        $this->actingAs($scholar)
            ->from(route('user.profile', ['tab' => 'academic-settings']))
            ->put(route('user.profile.academic'), ['program_start_year' => 2022])
            ->assertRedirect(route('user.profile', ['tab' => 'academic-settings']))
            ->assertSessionHas('success');

        $this->assertSame(2022, $scholar->fresh()->program_start_year);

        $tracking = app(ScholarService::class)->programHourTracking($scholar->fresh());
        $this->assertSame([2022, 2023, 2024, 2025], array_column($tracking['years'], 'year_start'));
        $this->assertSame('AY 2022–2023', $tracking['years'][0]['academic_year']);
        $this->assertSame('AY 2025–2026', $tracking['years'][3]['academic_year']);

        $this->actingAs($scholar)
            ->put(route('academic.period.update'), [
                'year_start' => 2026,
                'semester' => '2nd Semester',
            ])
            ->assertRedirect();

        $this->assertSame(2022, $scholar->fresh()->program_start_year);
        $this->assertSame([2022, 2023, 2024, 2025], array_column(
            app(ScholarService::class)->programHourTracking($scholar->fresh())['years'],
            'year_start'
        ));

        $scholar->forceFill(['year_level' => '4th Year'])->save();
        $this->assertSame(2022, $scholar->fresh()->program_start_year);
        $this->assertSame(4, app(ScholarService::class)->programYearForScholar($scholar->fresh()));
    }

    /**
     * @param  array<string, mixed>  $tracking
     * @return array<string, mixed>
     */
    private function semester(array $tracking, int $year, string $semester): array
    {
        foreach ($tracking['years'] as $yearRow) {
            if ((int) $yearRow['year_start'] !== $year) {
                continue;
            }

            foreach ($yearRow['semesters'] as $semesterRow) {
                if ($semesterRow['semester'] === $semester) {
                    return $semesterRow;
                }
            }
        }

        $this->fail("Missing {$semester} for {$year}.");
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'Progress Admin',
            'scholar_id' => 'ADMIN-PROGRESS',
            'email' => 'progress-admin@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    /**
     * @return array{0: ScholarshipProgram, 1: ScholarshipClub}
     */
    private function makeProgram(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-progress-'.uniqid(),
            'is_active' => true,
        ]);

        $club = ScholarshipClub::create([
            'name' => 'Dapa Progress Club',
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);

        return [$program, $club];
    }

    private function makeScholar(
        ScholarshipProgram $program,
        ScholarshipClub $club,
        string $name,
        string $email,
        string $scholarId,
        string $yearLevel,
        ?int $programStartYear = 0,
    ): User {
        $scholar = User::register([
            'full_name' => $name,
            'scholar_id' => $scholarId,
            'email' => $email,
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => $yearLevel,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        if ($programStartYear === null) {
            return $scholar->fresh();
        }

        $start = $programStartYear !== 0
            ? $programStartYear
            : (int) app(AcademicSettingsService::class)->current()->year_start
                - (ScholarService::programYearIndex($yearLevel) - 1);

        $scholar->forceFill(['program_start_year' => $start])->save();

        return $scholar->fresh();
    }

    private function makeAttendance(
        ScholarshipProgram $program,
        User $scholar,
        int $year,
        string $semester,
        float $hours,
        string $status,
    ): Attendance {
        $event = Event::create([
            'title' => $scholar->scholar_id.' '.$year.' '.$semester,
            'description' => 'Progress fixture.',
            'location' => 'Dapa',
            'starts_at' => now()->setDate($year, $semester === '1st Semester' ? 9 : 2, 15)->setTime(8, 0),
            'ends_at' => now()->setDate($year, $semester === '1st Semester' ? 9 : 2, 15)->setTime(12, 0),
            'service_hours' => $hours,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $program->id,
        ]);

        return Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'academic_year_start' => $year,
            'academic_year_end' => $year + 1,
            'semester' => $semester,
            'check_in' => $event->starts_at->copy()->addMinutes(5),
            'check_out' => $event->ends_at,
            'hours_earned' => $hours,
            'status' => $status,
        ]);
    }
}
