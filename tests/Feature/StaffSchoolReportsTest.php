<?php

namespace Tests\Feature;

use App\Models\AcademicSetting;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Support\PercentShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSchoolReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_percent_shares_add_up_to_one_hundred(): void
    {
        $this->assertSame(100, array_sum(PercentShare::allocate([
            'STI' => 3,
            'SNSU' => 1,
            'SPUS' => 1,
            'SDC' => 1,
            'SJTIT' => 1,
        ])));
    }

    public function test_all_four_reports_group_by_school_and_keep_period_filters(): void
    {
        [$staff, $sti, $snsu, $stiScholar, $snsuScholar] = $this->makeClubWithSchools();
        $this->makeApprovedHours($stiScholar, 5, 2026, '1st Semester');
        $this->makeApprovedHours($snsuScholar, 30, 2025, '1st Semester');

        $pages = [
            route('staff.reports.service-hours', ['year' => 2026, 'semester' => '1st Semester']),
            route('staff.reports.attendance', ['year' => 2026, 'semester' => '1st Semester']),
            route('staff.reports.participation', ['year' => 2026, 'semester' => '1st Semester']),
            route('staff.reports.completion', ['year' => 2026, 'semester' => '1st Semester']),
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($staff)
                ->get($url)
                ->assertOk()
                ->assertSee('All Schools/Universities')
                ->assertSee('School/University Reports')
                ->assertSee('STI — 50%')
                ->assertSee('Surigao del Norte State University — 50%')
                ->assertDontSee('Service Hours by Scholar')
                ->assertDontSee('Latest Attendance Records')
                ->assertDontSee('Scholar Completion')
                ->assertSee('name="school"', false)
                ->getContent();

            $this->assertStringContainsString('>'.$sti->name.'</option>', $html);
            $this->assertStringContainsString('>'.$snsu->name.'</option>', $html);
        }

        $this->actingAs($staff)
            ->get(route('staff.reports.service-hours', ['year' => 2026, 'semester' => '1st Semester']))
            ->assertOk()
            ->assertSee('Completed:')
            ->assertSee('In Progress:')
            ->assertSee('Not Started:');

        $this->actingAs($staff)
            ->get(route('staff.reports.completion', ['year' => 2026, 'semester' => '1st Semester']))
            ->assertOk()
            ->assertSee('STI — 50%')
            ->assertSee('Surigao del Norte State University — 50%');

        $this->actingAs($staff)
            ->get(route('staff.reports.completion', [
                'year' => 2026,
                'semester' => '1st Semester',
                'school' => 'id:'.$sti->id,
            ]))
            ->assertOk()
            ->assertSee($sti->name)
            ->assertDontSee('School/University Share')
            ->assertDontSee($stiScholar->full_name)
            ->assertDontSee($snsuScholar->full_name);
    }

    public function test_changing_a_scholar_school_moves_them_in_reports(): void
    {
        [$staff, $sti, $snsu, $stiScholar] = $this->makeClubWithSchools();

        $this->actingAs($staff)
            ->get(route('staff.reports.completion', ['year' => 2026, 'semester' => AcademicSettingsService::SEMESTER_ALL]))
            ->assertOk()
            ->assertSee('STI — 50%')
            ->assertSee('Surigao del Norte State University — 50%');

        $stiScholar->scholarship_club_school_id = $snsu->id;
        $stiScholar->school_university = $snsu->name;
        $stiScholar->save();

        $this->actingAs($staff)
            ->get(route('staff.reports.completion', [
                'year' => 2026,
                'semester' => AcademicSettingsService::SEMESTER_ALL,
                'school' => 'id:'.$snsu->id,
            ]))
            ->assertOk()
            ->assertSee('Surigao del Norte State University — 100%');

        $this->actingAs($staff)
            ->get(route('staff.reports.completion', [
                'year' => 2026,
                'semester' => AcademicSettingsService::SEMESTER_ALL,
                'school' => 'id:'.$sti->id,
            ]))
            ->assertOk()
            ->assertSee('STI — 0%');
    }

    public function test_service_hours_do_not_mix_schools_in_a_school_card(): void
    {
        [$staff, $sti, , $stiScholar, $snsuScholar] = $this->makeClubWithSchools();
        $this->makeApprovedHours($stiScholar, 8, 2026, '1st Semester');
        $this->makeApprovedHours($snsuScholar, 12, 2026, '1st Semester');

        $html = $this->actingAs($staff)
            ->get(route('staff.reports.service-hours', ['year' => 2026, 'semester' => '1st Semester']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('STI — 50%', $html);
        $this->assertStringContainsString('Surigao del Norte State University — 50%', $html);
        $this->assertStringContainsString('<strong>Total:</strong> 1', $html);
        $this->assertStringNotContainsString($stiScholar->full_name, $html);
        $this->assertStringNotContainsString($snsuScholar->full_name, $html);
    }

    /**
     * @return array{0: User, 1: ScholarshipClubSchool, 2: ScholarshipClubSchool, 3: User, 4: User}
     */
    private function makeClubWithSchools(): array
    {
        AcademicSetting::create([
            'year_start' => 2026,
            'year_end' => 2027,
            'semester' => '1st Semester',
            'is_active' => true,
        ]);

        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City, Surigao del Norte',
            'display_name' => 'Surigao City, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'sc-school-reports',
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $program->id);
        $sti = ScholarshipClubSchool::createForClub('STI', $club->id);
        $snsu = ScholarshipClubSchool::createForClub('Surigao del Norte State University', $club->id);

        $staff = User::register([
            'full_name' => 'Report Staff',
            'scholar_id' => 'STAFF-SCHOOL-REPORT',
            'email' => 'school-report-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $stiScholar = $this->makeScholar($program, $club, $sti, 'STI');
        $snsuScholar = $this->makeScholar($program, $club, $snsu, 'SNSU');

        return [$staff, $sti, $snsu, $stiScholar, $snsuScholar];
    }

    private function makeScholar(ScholarshipProgram $program, ScholarshipClub $club, ScholarshipClubSchool $school, string $suffix): User
    {
        return User::register([
            'full_name' => 'Scholar '.$suffix,
            'scholar_id' => 'SCH-SCHOOL-'.$suffix,
            'email' => 'scholar-school-'.strtolower($suffix).'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'school_university' => $school->name,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
    }

    private function makeApprovedHours(User $scholar, float $hours, int $year, string $semester): Attendance
    {
        $event = Event::create([
            'title' => $scholar->full_name.' Event',
            'description' => 'School report fixture.',
            'location' => 'Surigao City',
            'starts_at' => now()->setYear($year)->setMonth(8)->setDay(10)->setTime(8, 0),
            'ends_at' => now()->setYear($year)->setMonth(8)->setDay(10)->setTime(12, 0),
            'service_hours' => $hours,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $scholar->scholarship_program_id,
        ]);

        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
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
            'status' => Attendance::STATUS_APPROVED,
        ]);
    }
}
