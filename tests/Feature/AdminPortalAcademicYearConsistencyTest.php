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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPortalAcademicYearConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_academic_year_persists_across_admin_features_and_filters_records(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $current = $academic->current();
        $other = $academic->createYear('2025–2026', $admin);

        [$program, $club, $scholarCurrent, $scholarOther] = $this->seedYearScopedRecords($current->year_start, $other->year_start);

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->put(route('academic.period.update'), [
                'year_start' => $other->year_start,
                'semester' => '1st Semester',
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success');

        $this->assertSame($other->year_start, $academic->selectedPeriod()->year_start);

        $filteredPages = [
            'admin.dashboard',
            'admin.scholars',
            'admin.staff',
            'admin.events',
            'admin.service-hours',
            'admin.documents',
            'admin.reports',
        ];

        foreach ($filteredPages as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk()
                ->assertDontSee('admin-academic-year-selector', false)
                ->assertDontSee('aria-label="Semester"', false)
                ->assertDontSee('class="academic-period-filter"', false);
        }

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('admin-academic-year-selector', false)
            ->assertSee('AY '.$other->periodLabel())
            ->assertDontSee('aria-label="Semester"', false);

        $scholarsHtml = $this->actingAs($admin)
            ->get(route('admin.scholars'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($scholarOther->full_name, $scholarsHtml);
        $this->assertStringNotContainsString($scholarCurrent->full_name, $scholarsHtml);

        $eventsHtml = $this->actingAs($admin)
            ->get(route('admin.events'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Other AY Event', $eventsHtml);
        $this->assertStringNotContainsString('Current AY Event', $eventsHtml);

        $documentsHtml = $this->actingAs($admin)
            ->get(route('admin.documents'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Other Doc', $documentsHtml);
        $this->assertStringContainsString($scholarOther->full_name, $documentsHtml);
        $this->assertStringNotContainsString('Current Doc', $documentsHtml);
        $this->assertStringNotContainsString($scholarCurrent->full_name, $documentsHtml);

        $hoursHtml = $this->actingAs($admin)
            ->get(route('admin.service-hours'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('AY '.$other->year_start.'–'.$other->year_end, $hoursHtml);

        // Location + club filters still work with the selected year.
        $this->actingAs($admin)
            ->get(route('admin.scholars', [
                'location' => $program->id,
                'club' => $club->id,
            ]))
            ->assertOk()
            ->assertSee($scholarOther->full_name)
            ->assertDontSee($scholarCurrent->full_name);

        // Switching back restores the other year's records.
        $this->actingAs($admin)
            ->from(route('admin.events'))
            ->put(route('academic.period.update'), [
                'year_start' => $current->year_start,
                'semester' => '1st Semester',
            ])
            ->assertRedirect(route('admin.events'));

        $this->actingAs($admin)
            ->get(route('admin.events'))
            ->assertOk()
            ->assertSee('Current AY Event')
            ->assertDontSee('Other AY Event');

        $this->actingAs($admin)
            ->get(route('admin.scholars'))
            ->assertOk()
            ->assertSee($scholarCurrent->full_name)
            ->assertDontSee($scholarOther->full_name);
    }

    /**
     * @return array{0: ScholarshipProgram, 1: ScholarshipClub, 2: User, 3: User}
     */
    private function seedYearScopedRecords(int $currentYear, int $otherYear): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City, Surigao del Norte',
            'display_name' => 'Surigao City, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-portal-ay-'.uniqid(),
            'is_active' => true,
        ]);

        $club = ScholarshipClub::create([
            'name' => 'Portal AY Club',
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);

        $scholarCurrent = User::register([
            'full_name' => 'Current Year Scholar',
            'scholar_id' => 'BSSA-CUR-001',
            'email' => 'current-year-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
        $scholarCurrent->forceFill(['program_start_year' => $currentYear])->save();

        $scholarOther = User::register([
            'full_name' => 'Other Year Scholar',
            'scholar_id' => 'BSSA-OTH-001',
            'email' => 'other-year-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
        // Keep this scholar outside the current year's 4-year window so year switching is measurable.
        $scholarOther->forceFill(['program_start_year' => $otherYear - 3])->save();

        $eventCurrent = Event::create([
            'title' => 'Current AY Event',
            'description' => 'Current',
            'location' => 'Surigao City',
            'starts_at' => Carbon::create($currentYear, 9, 10, 9),
            'ends_at' => Carbon::create($currentYear, 9, 10, 12),
            'service_hours' => 4,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $program->id,
        ]);

        $eventOther = Event::create([
            'title' => 'Other AY Event',
            'description' => 'Other',
            'location' => 'Surigao City',
            'starts_at' => Carbon::create($otherYear, 9, 10, 9),
            'ends_at' => Carbon::create($otherYear, 9, 10, 12),
            'service_hours' => 4,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $program->id,
        ]);

        Attendance::create([
            'user_id' => $scholarCurrent->id,
            'event_id' => $eventCurrent->id,
            'academic_year_start' => $currentYear,
            'academic_year_end' => $currentYear + 1,
            'semester' => '1st Semester',
            'check_in' => Carbon::create($currentYear, 9, 10, 9, 5),
            'hours_earned' => 4,
            'status' => Attendance::STATUS_APPROVED,
        ]);

        Attendance::create([
            'user_id' => $scholarOther->id,
            'event_id' => $eventOther->id,
            'academic_year_start' => $otherYear,
            'academic_year_end' => $otherYear + 1,
            'semester' => '1st Semester',
            'check_in' => Carbon::create($otherYear, 9, 10, 9, 5),
            'hours_earned' => 4,
            'status' => Attendance::STATUS_APPROVED,
        ]);

        $typeCurrent = DocumentType::create([
            'name' => 'Current Doc',
            'slug' => 'current-doc-'.$program->id,
            'scholarship_program_id' => $program->id,
            'required' => true,
        ]);
        $typeOther = DocumentType::create([
            'name' => 'Other Doc',
            'slug' => 'other-doc-'.$program->id,
            'scholarship_program_id' => $program->id,
            'required' => true,
        ]);

        Document::create([
            'user_id' => $scholarCurrent->id,
            'document_type_id' => $typeCurrent->id,
            'academic_year_start' => $currentYear,
            'academic_year_end' => $currentYear + 1,
            'status' => 'pending',
            'original_name' => 'current-year.pdf',
            'uploaded_at' => now(),
        ]);

        Document::create([
            'user_id' => $scholarOther->id,
            'document_type_id' => $typeOther->id,
            'academic_year_start' => $otherYear,
            'academic_year_end' => $otherYear + 1,
            'status' => 'pending',
            'original_name' => 'other-year.pdf',
            'uploaded_at' => now()->subYear(),
        ]);

        return [$program, $club, $scholarCurrent, $scholarOther];
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'Portal AY Admin',
            'scholar_id' => 'ADMIN-PORTAL-AY-001',
            'email' => 'admin-portal-ay@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }
}
