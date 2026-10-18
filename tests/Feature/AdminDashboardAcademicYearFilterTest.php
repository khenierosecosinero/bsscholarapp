<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Services\AdminDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardAcademicYearFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_stats_only_include_the_selected_academic_year(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $current = $academic->current();
        $other = $academic->createYear('2025–2026', $admin);

        [$program, $club, $scholar] = $this->makeScholarContext();

        $typeCurrent = DocumentType::create([
            'scholarship_program_id' => $program->id,
            'name' => 'Certificate of Enrollment',
            'slug' => 'coe-current-'.$program->id,
            'required' => true,
        ]);
        $typeOther = DocumentType::create([
            'scholarship_program_id' => $program->id,
            'name' => 'Transcript of Records',
            'slug' => 'tor-other-'.$program->id,
            'required' => true,
        ]);

        Document::create([
            'user_id' => $scholar->id,
            'document_type_id' => $typeCurrent->id,
            'academic_year_start' => $current->year_start,
            'academic_year_end' => $current->year_end,
            'status' => 'pending',
            'uploaded_at' => now(),
            'original_name' => 'current.pdf',
        ]);

        Document::create([
            'user_id' => $scholar->id,
            'document_type_id' => $typeOther->id,
            'academic_year_start' => $other->year_start,
            'academic_year_end' => $other->year_end,
            'status' => 'pending',
            'uploaded_at' => now()->subYear(),
            'original_name' => 'other.pdf',
        ]);

        Event::create([
            'title' => 'Current AY Event',
            'description' => 'In selected year',
            'location' => 'Surigao City',
            'starts_at' => Carbon::create($current->year_start, 9, 15, 9),
            'ends_at' => Carbon::create($current->year_start, 9, 15, 12),
            'service_hours' => 4,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $program->id,
        ]);

        Event::create([
            'title' => 'Other AY Event',
            'description' => 'Other year',
            'location' => 'Surigao City',
            'starts_at' => Carbon::create($other->year_start, 9, 15, 9),
            'ends_at' => Carbon::create($other->year_start, 9, 15, 12),
            'service_hours' => 4,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $program->id,
        ]);

        $eventCurrent = Event::query()->where('title', 'Current AY Event')->firstOrFail();
        $eventOther = Event::query()->where('title', 'Other AY Event')->firstOrFail();

        Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $eventCurrent->id,
            'academic_year_start' => $current->year_start,
            'academic_year_end' => $current->year_end,
            'semester' => '1st Semester',
            'check_in' => Carbon::create($current->year_start, 9, 15, 9, 5),
            'hours_earned' => 4,
            'status' => Attendance::STATUS_APPROVED,
        ]);

        Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $eventOther->id,
            'academic_year_start' => $other->year_start,
            'academic_year_end' => $other->year_end,
            'semester' => '1st Semester',
            'check_in' => Carbon::create($other->year_start, 9, 15, 9, 5),
            'hours_earned' => 8,
            'status' => Attendance::STATUS_APPROVED,
        ]);

        $academic->rememberSelectedPeriod((int) $current->year_start, '1st Semester');
        $statsCurrent = app(AdminDashboardService::class)->dashboardStats([$program->id]);

        $this->assertSame(1, (int) $statsCurrent['total_events']);
        $this->assertSame(1, (int) $statsCurrent['total_documents']);
        $this->assertSame(1, (int) $statsCurrent['pending_documents']);
        $this->assertSame('4.00', $statsCurrent['total_service_hours']);
        $this->assertSame($current->year_start, (int) $statsCurrent['period']['year_start']);

        $academic->rememberSelectedPeriod((int) $other->year_start, '1st Semester');
        $statsOther = app(AdminDashboardService::class)->dashboardStats([$program->id]);

        $this->assertSame(1, (int) $statsOther['total_events']);
        $this->assertSame(1, (int) $statsOther['total_documents']);
        $this->assertSame('8.00', $statsOther['total_service_hours']);
        $this->assertSame($other->year_start, (int) $statsOther['period']['year_start']);

        $this->actingAs($admin)
            ->withSession([
                AcademicSettingsService::SESSION_KEY => [
                    'year_start' => $other->year_start,
                    'semester' => '1st Semester',
                ],
            ])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Showing Administration data for')
            ->assertSee('AY '.$other->year_start.'–'.$other->year_end);
    }

    /**
     * @return array{0: ScholarshipProgram, 1: ScholarshipClub, 2: User}
     */
    private function makeScholarContext(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City, Surigao del Norte',
            'display_name' => 'Surigao City, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-ay-dash-'.uniqid(),
            'is_active' => true,
        ]);

        $club = ScholarshipClub::create([
            'name' => 'AY Dashboard Club',
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);

        $scholar = User::register([
            'full_name' => 'AY Dashboard Scholar',
            'scholar_id' => 'BSSA-AY-DASH-001',
            'email' => 'ay-dash-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        return [$program, $club, $scholar];
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'AY Dash Admin',
            'scholar_id' => 'ADMIN-AY-DASH-001',
            'email' => 'admin-ay-dash@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }
}
