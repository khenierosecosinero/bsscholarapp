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
            route('staff.reports.service-hours'),
            route('staff.reports.attendance'),
            route('staff.reports.participation'),
            route('staff.reports.completion'),
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($staff)
                ->get($url)
                ->assertOk()
                ->assertSee('All Schools / Universities')
                ->assertSee('School/University Reports')
                ->assertSee('STI — 50%')
                ->assertSee('Surigao del Norte State University — 50%')
                ->assertDontSee('Service Hours by Scholar')
                ->assertDontSee('Latest Attendance Records')
                ->assertDontSee('Scholar Completion')
                ->assertSee('name="school"', false)
                ->assertSee('id="report-year"', false)
                ->assertSee('All Academic Years')
                ->assertSee('2026–2027')
                ->assertDontSee('id="report-semester"', false)
                ->assertSee('Set in Settings → General Settings')
                ->getContent();

            $this->assertStringContainsString('>'.$sti->name.'</option>', $html);
            $this->assertStringContainsString('>'.$snsu->name.'</option>', $html);
        }

        $this->actingAs($staff)
            ->get(route('staff.reports.service-hours'))
            ->assertOk()
            ->assertSee('Completed:')
            ->assertSee('In Progress:')
            ->assertSee('Not Started:');

        $this->actingAs($staff)
            ->get(route('staff.reports.completion'))
            ->assertOk()
            ->assertSee('STI — 50%')
            ->assertSee('Surigao del Norte State University — 50%');

        $this->actingAs($staff)
            ->get(route('staff.reports.completion', [
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
            ->get(route('staff.reports.completion'))
            ->assertOk()
            ->assertSee('STI — 50%')
            ->assertSee('Surigao del Norte State University — 50%');

        $stiScholar->scholarship_club_school_id = $snsu->id;
        $stiScholar->school_university = $snsu->name;
        $stiScholar->save();

        $this->actingAs($staff)
            ->get(route('staff.reports.completion', [
                'school' => 'id:'.$snsu->id,
            ]))
            ->assertOk()
            ->assertSee('Surigao del Norte State University — 100%');

        $this->actingAs($staff)
            ->get(route('staff.reports.completion', [
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
            ->get(route('staff.reports.service-hours'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('STI — 50%', $html);
        $this->assertStringContainsString('Surigao del Norte State University — 50%', $html);
        $this->assertStringContainsString('<strong>Total:</strong> 1', $html);
        $this->assertStringNotContainsString($stiScholar->full_name, $html);
        $this->assertStringNotContainsString($snsuScholar->full_name, $html);
    }

    public function test_academic_year_dropdown_filters_reports_without_changing_the_global_setting(): void
    {
        [$staff, $sti, $snsu, $stiScholar, $snsuScholar] = $this->makeClubWithSchools();
        $this->makeApprovedHours($stiScholar, 5, 2026, '1st Semester');
        $this->makeApprovedHours($snsuScholar, 30, 2025, '1st Semester');

        AcademicSetting::create([
            'year_start' => 2025,
            'year_end' => 2026,
            'semester' => '1st Semester',
            'is_active' => false,
        ]);
        AcademicSetting::create([
            'year_start' => 2024,
            'year_end' => 2025,
            'semester' => '1st Semester',
            'is_active' => false,
        ]);

        $pages = [
            'staff.reports.service-hours',
            'staff.reports.attendance',
            'staff.reports.participation',
            'staff.reports.completion',
        ];

        foreach ($pages as $routeName) {
            $this->actingAs($staff)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('Academic Year')
                ->assertSee('All Academic Years')
                ->assertSee('2024–2025')
                ->assertSee('2025–2026')
                ->assertSee('2026–2027')
                ->assertSee('value="2026" selected', false);
        }

        $year2026 = $this->actingAs($staff)
            ->get(route('staff.reports.service-hours', ['year' => 2026, 'school' => 'id:'.$sti->id]));
        $year2026->assertOk()->assertSee('5.00');
        $this->assertSame(5.0, $year2026->viewData('report')['overview']['approved_hours']);
        $this->assertSame('id:'.$sti->id, $year2026->viewData('selectedSchoolKey'));

        $year2025 = $this->actingAs($staff)
            ->get(route('staff.reports.service-hours', ['year' => 2025, 'school' => 'all']));
        $year2025->assertOk();
        $this->assertSame(30.0, $year2025->viewData('report')['overview']['approved_hours']);
        $this->assertFalse($year2025->viewData('report')['empty']);

        $allYears = $this->actingAs($staff)
            ->get(route('staff.reports.service-hours', ['year' => 'all', 'school' => 'all']));
        $allYears->assertOk()->assertSee('All Academic Years');
        $this->assertSame(35.0, $allYears->viewData('report')['overview']['approved_hours']);

        $attendance2025 = $this->actingAs($staff)
            ->get(route('staff.reports.attendance', ['year' => 2025, 'school' => 'all']));
        $this->assertSame(1, $attendance2025->viewData('report')['breakdown']['approved']);

        $attendanceAll = $this->actingAs($staff)
            ->get(route('staff.reports.attendance', ['year' => 'all', 'school' => 'all']));
        $this->assertSame(2, $attendanceAll->viewData('report')['breakdown']['approved']);

        $participation2026 = $this->actingAs($staff)
            ->get(route('staff.reports.participation', ['year' => 2026, 'school' => 'all']));
        $this->assertSame(1, $participation2026->viewData('report')['registered']);
        $this->assertSame(1, $participation2026->viewData('report')['participated']);

        $participationAll = $this->actingAs($staff)
            ->get(route('staff.reports.participation', ['year' => 'all', 'school' => 'all']));
        $this->assertSame(2, $participationAll->viewData('report')['registered']);

        $empty = $this->actingAs($staff)
            ->get(route('staff.reports.service-hours', ['year' => 2024, 'school' => 'all']));
        $empty->assertOk()->assertSee('No records for AY 2024–2025.');
        $this->assertTrue($empty->viewData('report')['empty']);
        $this->assertSame(0.0, $empty->viewData('report')['overview']['approved_hours']);

        $this->actingAs($staff)
            ->get(route('staff.reports.attendance', ['year' => 2024, 'school' => 'all']))
            ->assertOk()
            ->assertSee('No records for AY 2024–2025.');

        $this->actingAs($staff)
            ->get(route('staff.reports.completion', ['year' => 2024, 'school' => 'all']))
            ->assertOk()
            ->assertSee('No records for AY 2024–2025.');

        $this->actingAs($staff)
            ->get(route('staff.reports.participation', ['year' => 2024, 'school' => 'all']))
            ->assertOk()
            ->assertSee('No records for AY 2024–2025.');

        $fallback = $this->actingAs($staff)
            ->get(route('staff.reports.service-hours', ['year' => 1999, 'school' => 'all']));
        $this->assertSame(2026, $fallback->viewData('reportFilter')['year_start']);
        $this->assertSame(5.0, $fallback->viewData('report')['overview']['approved_hours']);

        $this->assertSame(2026, AcademicSetting::query()->where('is_active', true)->value('year_start'));
        $this->assertNull(session('academic_period_view'));

        $stillCurrent = $this->actingAs($staff)
            ->get(route('staff.reports.service-hours', ['school' => 'all']));
        $this->assertSame(2026, $stillCurrent->viewData('reportFilter')['year_start']);
        $this->assertSame(5.0, $stillCurrent->viewData('report')['overview']['approved_hours']);
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
