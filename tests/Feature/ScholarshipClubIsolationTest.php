<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipClubIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_labels_do_not_call_provincial_clubs_a_scholarship_program(): void
    {
        $city = $this->makeCity('Surigao City', 'Surigao del Norte', 'sc-label');
        $dapa = $this->makeCity('Dapa', 'Surigao del Norte', 'dapa-label');
        $province = ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'sdn-label',
            'is_active' => true,
        ]);
        $sur = ScholarshipProgram::create([
            'location_name' => 'Surigao del Sur',
            'location_type' => 'province',
            'name' => 'Surigao del Sur',
            'display_name' => 'Surigao del Sur (Province)',
            'slug' => 'sds-label',
            'is_active' => true,
        ]);

        $this->assertSame('Surigao City Scholarship Program', $city->programLabel());
        $this->assertSame('Surigao City Scholarship Program', $city->programTypeLabel());
        $this->assertSame('Scholarship Club', $dapa->programTypeLabel());
        $this->assertSame('Dapa, Surigao del Norte', $dapa->programLabel());
        $this->assertSame('Surigao del Norte', $province->programLabel());
        $this->assertSame('Scholarship Club', $province->programTypeLabel());
        $this->assertSame('Surigao del Sur', $sur->programLabel());
        $this->assertStringNotContainsString('Province Scholar Program', $province->programLabel());
        $this->assertStringNotContainsString('Province Scholar Program', $sur->programLabel());
    }

    public function test_each_scholarship_club_keeps_its_own_events_and_documents(): void
    {
        $dapa = $this->makeCity('Dapa', 'Surigao del Norte', 'dapa-isolate');
        $coastal = $this->makeClub($dapa, 'Coastal Scholars Club');
        $inland = $this->makeClub($dapa, 'Inland Scholars Club');
        $coastalStaff = $this->makeStaff($dapa, $coastal, 'Coastal Staff', 'coastal-staff@example.com', 'STAFF-COAST-1');
        $inlandStaff = $this->makeStaff($dapa, $inland, 'Inland Staff', 'inland-staff@example.com', 'STAFF-INLAND-1');
        $coastalScholar = $this->makeScholar($dapa, $coastal, 'Coastal Scholar', 'coastal-scholar@example.com', 'SCH-COAST-1');
        $inlandScholar = $this->makeScholar($dapa, $inland, 'Inland Scholar', 'inland-scholar@example.com', 'SCH-INLAND-1');

        $this->actingAs($coastalStaff)
            ->post(route('staff.events.store'), [
                'title' => 'Coastal Cleanup',
                'location' => 'Dapa Boulevard',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
                'service_hours' => 3,
            ])
            ->assertRedirect();

        $event = Event::query()->where('title', 'Coastal Cleanup')->firstOrFail();
        $this->assertSame($coastal->id, $event->scholarship_club_id);

        $this->actingAs($coastalStaff)
            ->get(route('staff.events'))
            ->assertOk()
            ->assertSee('Coastal Cleanup');

        $this->actingAs($inlandStaff)
            ->get(route('staff.events'))
            ->assertOk()
            ->assertDontSee('Coastal Cleanup');

        $this->actingAs($inlandStaff)
            ->get(route('staff.events.show', $event))
            ->assertForbidden();

        $this->actingAs($coastalScholar)
            ->get(route('user.events'))
            ->assertOk()
            ->assertSee('Coastal Cleanup');

        $this->actingAs($inlandScholar)
            ->get(route('user.events'))
            ->assertOk()
            ->assertDontSee('Coastal Cleanup');

        $this->actingAs($coastalStaff)
            ->post(route('staff.documents.store'), [
                'name' => 'Coastal Barangay Clearance',
                'description' => 'For the coastal club only',
                'required' => 1,
            ])
            ->assertRedirect();

        $type = DocumentType::query()->where('name', 'Coastal Barangay Clearance')->firstOrFail();
        $this->assertSame($coastal->id, $type->scholarship_club_id);

        $this->actingAs($coastalScholar)->get(route('user.documents'))->assertOk()->assertSee('Coastal Barangay Clearance');
        $this->actingAs($inlandScholar)->get(route('user.documents'))->assertOk()->assertDontSee('Coastal Barangay Clearance');
        $this->actingAs($inlandStaff)->get(route('staff.documents'))->assertOk()->assertDontSee('Coastal Barangay Clearance');
    }

    private function makeCity(string $name, string $province, string $slug): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => $name,
            'location_type' => 'city_municipality',
            'name' => $name === 'Surigao City' ? 'Surigao City Scholarship Program' : "{$name}, {$province}",
            'display_name' => "{$name}, {$province}",
            'province_name' => $province,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function makeClub(ScholarshipProgram $program, string $name): ScholarshipClub
    {
        return ScholarshipClub::createForProgram($name, $program->id);
    }

    private function makeStaff(ScholarshipProgram $program, ScholarshipClub $club, string $name, string $email, string $scholarId): User
    {
        return User::register([
            'full_name' => $name,
            'scholar_id' => $scholarId,
            'email' => $email,
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => $program->location_name,
            'province' => $program->province_name,
        ]);
    }

    private function makeScholar(ScholarshipProgram $program, ScholarshipClub $club, string $name, string $email, string $scholarId): User
    {
        return User::register([
            'full_name' => $name,
            'scholar_id' => $scholarId,
            'email' => $email,
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => $program->location_name,
            'province' => $program->province_name,
        ]);
    }
}
