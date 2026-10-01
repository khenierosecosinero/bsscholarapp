<?php

namespace Tests\Feature;

use App\Models\AcademicSetting;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAcademicYearSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_shows_active_academic_year_from_the_database(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $current = $academic->current();

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Academic Year')
            ->assertSee('Active: '.$current->periodLabel())
            ->assertSee('Add Academic Year')
            ->assertSee('Delete')
            ->assertSee('placeholder="2026–2027"', false)
            ->assertDontSee('Current Admin Scope')
            ->assertDontSee('Showing City Scholar records')
            ->assertDontSee('City Scholar — All Locations')
            ->assertDontSee('admin-location-pill', false)
            ->assertDontSee('The active academic year is used for scholars');
    }

    public function test_admin_can_add_an_academic_year(): void
    {
        $admin = $this->makeAdmin();
        app(AcademicSettingsService::class)->current();

        $this->actingAs($admin)
            ->post(route('admin.settings.academic-years.store'), [
                'academic_year' => '2027-2028',
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('academic_settings', [
            'year_start' => 2027,
            'year_end' => 2028,
            'is_active' => false,
        ]);
    }

    public function test_duplicate_and_invalid_academic_years_are_rejected(): void
    {
        $admin = $this->makeAdmin();
        $current = app(AcademicSettingsService::class)->current();

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.academic-years.store'), [
                'academic_year' => $current->periodLabel(),
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasErrors('academic_year');

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.academic-years.store'), [
                'academic_year' => '2026-2028',
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasErrors('academic_year');

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.academic-years.store'), [
                'academic_year' => 'not-a-year',
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasErrors('academic_year');
    }

    public function test_activating_an_academic_year_deactivates_the_previous_one(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $first = $academic->current();
        $next = $academic->createYear('2027–2028', $admin);

        $this->assertFalse($next->fresh()->is_active);
        $this->assertTrue($first->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('admin.settings.academic-years.activate', $next))
            ->assertRedirect(route('admin.settings'));

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($next->fresh()->is_active);
        $this->assertSame($next->id, $academic->current()->id);
        $this->assertSame([$first->year_start, $next->year_start], array_keys($academic->yearOptions()));
    }

    public function test_admin_can_edit_an_academic_year(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $year = $academic->createYear('2027-2028', $admin);

        $this->actingAs($admin)
            ->put(route('admin.settings.academic-years.update', $year), [
                'academic_year' => '2028–2029',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertDatabaseHas('academic_settings', [
            'id' => $year->id,
            'year_start' => 2028,
            'year_end' => 2029,
        ]);
    }

    public function test_the_last_academic_year_cannot_be_deactivated(): void
    {
        $admin = $this->makeAdmin();
        $current = app(AcademicSettingsService::class)->current();

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.academic-years.deactivate', $current))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasErrors('academic_year');

        $this->assertTrue($current->fresh()->is_active);
    }

    public function test_deactivating_an_academic_year_activates_another_registered_year(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $first = $academic->current();
        $next = $academic->createYear('2027–2028', $admin);
        $academic->activate($next, $admin);

        $this->actingAs($admin)
            ->post(route('admin.settings.academic-years.deactivate', $next))
            ->assertRedirect(route('admin.settings'));

        $this->assertFalse($next->fresh()->is_active);
        $this->assertTrue($first->fresh()->is_active);
        $this->assertSame($first->id, $academic->current()->id);
    }

    public function test_year_options_come_from_registered_academic_years_not_a_hardcoded_range(): void
    {
        AcademicSetting::query()->delete();
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $academic->clearCache();
        $academic->createYear('2030–2031', $admin);

        $options = $academic->yearOptions();

        $this->assertSame([2030 => '2030–2031'], $options);
        $this->assertArrayNotHasKey((int) now()->year, $options);
    }

    public function test_admin_can_delete_an_inactive_academic_year(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $current = $academic->current();
        $extra = $academic->createYear('2027–2028', $admin);

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->delete(route('admin.settings.academic-years.destroy', $extra))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('academic_settings', ['id' => $extra->id]);
        $this->assertDatabaseHas('academic_settings', ['id' => $current->id, 'is_active' => true]);
        $this->assertTrue($current->fresh()->is_active);
    }

    public function test_the_active_academic_year_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $current = $academic->current();
        $academic->createYear('2027–2028', $admin);

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->delete(route('admin.settings.academic-years.destroy', $current))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasErrors('academic_year');

        $this->assertDatabaseHas('academic_settings', ['id' => $current->id, 'is_active' => true]);
    }

    public function test_deleting_an_academic_year_warns_when_records_exist_and_does_not_remove_them(): void
    {
        $admin = $this->makeAdmin();
        $academic = app(AcademicSettingsService::class);
        $current = $academic->current();
        $extra = $academic->createYear('2027–2028', $admin);

        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City, Surigao del Norte',
            'display_name' => 'Surigao City, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-ay-delete',
            'is_active' => true,
        ]);

        $scholar = User::register([
            'full_name' => 'Year Scholar',
            'scholar_id' => 'BSSA-AY-DEL-001',
            'email' => 'ay-delete-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
        $scholar->forceFill([
            'academic_year_start' => 2027,
            'semester' => '2nd Semester',
        ])->save();

        $event = Event::create([
            'title' => 'Leadership Training',
            'description' => 'AY delete warning event.',
            'location' => 'Surigao City',
            'starts_at' => Carbon::create(2027, 9, 15, 9),
            'ends_at' => Carbon::create(2027, 9, 15, 12),
            'service_hours' => 4,
            'organizer' => 'BSSA',
            'status' => 'upcoming',
            'scholarship_program_id' => $program->id,
        ]);

        $attendance = Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'academic_year_start' => 2027,
            'academic_year_end' => 2028,
            'semester' => '2nd Semester',
            'check_in' => Carbon::create(2027, 9, 15, 9),
            'check_out' => Carbon::create(2027, 9, 15, 12),
            'hours_earned' => 4,
            'status' => Attendance::STATUS_APPROVED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Delete 2027–2028?')
            ->assertSee('This academic year is already used by')
            ->assertSee('attendance record')
            ->assertSee('Those records will stay in the system.');

        $this->actingAs($admin)
            ->delete(route('admin.settings.academic-years.destroy', $extra))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('academic_settings', ['id' => $extra->id]);
        $this->assertDatabaseHas('academic_settings', ['id' => $current->id]);
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'academic_year_start' => 2027]);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $this->assertNull($scholar->fresh()->academic_year_start);
        $this->assertNull($scholar->fresh()->semester);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-AY-001',
            'email' => 'admin-academic-year@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }
}
