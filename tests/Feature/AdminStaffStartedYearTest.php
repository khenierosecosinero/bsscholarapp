<?php

namespace Tests\Feature;

use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffStartedYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_registration_does_not_collect_started_year(): void
    {
        $program = $this->makeCityProgram();

        $this->get(route('register.staff'))
            ->assertOk()
            ->assertDontSee('Started Year')
            ->assertDontSee('name="staff_started_year"', false)
            ->assertDontSee('Select Started Year');

        $this->post(route('register.staff.post'), [
            'full_name' => 'Started Year Staff',
            'scholarship_program_id' => $program->id,
            'scholarship_club_name' => 'Started Year Club',
            'email' => 'started-year-staff@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $staff = User::query()->where('email', 'started-year-staff@example.com')->first();

        $this->assertNotNull($staff);
        $this->assertTrue($staff->isScholarStaff());
        $this->assertNull($staff->staff_started_year);
        $this->assertNull($staff->staffStartedYearLabel());
    }

    public function test_admin_can_view_and_update_staff_started_year(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $current = $academic->current();
        $next = $academic->createYear('2027–2028', $admin);
        $staff = $this->makeStaff($current->year_start);

        $this->actingAs($admin)
            ->get(route('admin.staff'))
            ->assertOk()
            ->assertSee('Started Year')
            ->assertSee($staff->staffStartedYearLabel());

        $this->actingAs($admin)
            ->get(route('admin.staff.show', $staff))
            ->assertOk()
            ->assertSee('Started Year')
            ->assertSee($staff->staffStartedYearLabel())
            ->assertSee('Update Started Year')
            ->assertSee('AY '.$next->periodLabel());

        $this->actingAs($admin)
            ->from(route('admin.staff.show', $staff))
            ->put(route('admin.staff.started-year', $staff), [
                'staff_started_year' => $next->year_start,
            ])
            ->assertRedirect(route('admin.staff.show', $staff))
            ->assertSessionHas('success');

        $this->assertSame((int) $next->year_start, (int) $staff->fresh()->staff_started_year);

        $this->actingAs($admin)
            ->put(route('admin.staff.started-year', $staff), [
                'staff_started_year' => '',
            ])
            ->assertRedirect(route('admin.staff.show', $staff));

        $this->assertNull($staff->fresh()->staff_started_year);
        $this->assertNull($staff->fresh()->staffStartedYearLabel());

        $this->actingAs($admin)
            ->get(route('admin.staff'))
            ->assertOk()
            ->assertSee($staff->full_name)
            ->assertSee('—');
    }

    public function test_existing_staff_without_started_year_still_display(): void
    {
        $admin = $this->makeAdmin();
        $staff = $this->makeStaff(null);

        $this->assertNull($staff->staff_started_year);

        $this->actingAs($admin)
            ->get(route('admin.staff.show', $staff))
            ->assertOk()
            ->assertSee('Not set');
    }

    private function makeCityProgram(): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-staff-started-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function makeStaff(?int $startedYear): User
    {
        $program = $this->makeCityProgram();

        $attributes = [
            'full_name' => 'Listed Staff Member',
            'email' => 'listed-staff-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'cellphone_number' => '09170001111',
            'scholarship_program_id' => $program->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ];

        if ($startedYear !== null) {
            $attributes['staff_started_year'] = $startedYear;
        }

        $staff = User::register($attributes);

        return $staff->fresh();
    }

    private function makeAdmin(): User
    {
        $suffix = uniqid();
        $admin = User::register([
            'full_name' => 'Staff Year Admin',
            'scholar_id' => 'ADMIN-STAFF-YEAR-'.$suffix,
            'email' => 'admin-staff-year-'.$suffix.'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }
}
