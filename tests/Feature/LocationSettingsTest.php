<?php

namespace Tests\Feature;

use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scholar_location_uses_surigao_provinces_and_persists_street(): void
    {
        $norte = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-location', null);
        $this->makeProgram('province', 'Surigao del Sur', 'Surigao del Sur', 'sds-location', null);
        $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-location', 'Surigao del Norte');
        $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-location', 'Surigao del Sur');
        $this->makeProgram('city_municipality', 'Manila', 'Manila', 'manila-location', 'Metro Manila');

        $user = User::register([
            'full_name' => 'Location Scholar',
            'scholar_id' => 'SCH-LOC-001',
            'email' => 'location-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $norte->id,
            'province' => 'Surigao del Norte',
            'city' => 'Old Town',
        ]);

        $this->actingAs($user)
            ->get(route('user.profile'))
            ->assertOk()
            ->assertSee('No./Street')
            ->assertSee('name="street"', false)
            ->assertSee('name="province"', false)
            ->assertSee('>Surigao del Norte</option>', false)
            ->assertSee('>Surigao del Sur</option>', false)
            ->assertSee('>Alegria</option>', false)
            ->assertSee('>Dapa</option>', false)
            ->assertSee('>Old Town</option>', false)
            ->assertDontSee('>Tandag</option>', false)
            ->assertDontSee('>Manila</option>', false)
            ->assertDontSee('>Region<', false)
            ->assertDontSee('name="region"', false);

        $this->actingAs($user)
            ->put(route('user.profile.update'), [
                'street' => '  12 Rizal Street  ',
                'province' => 'Surigao del Sur',
                'city' => 'Tandag',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('12 Rizal Street', $user->street);
        $this->assertSame('Surigao del Sur', $user->province);
        $this->assertSame('Tandag', $user->city);
        $this->assertSame($norte->id, $user->scholarship_program_id);

        $this->actingAs($user->fresh())
            ->get(route('user.profile'))
            ->assertOk()
            ->assertSee('value="12 Rizal Street"', false)
            ->assertSee('>Tandag</option>', false);

        $this->actingAs($user)
            ->from(route('user.profile'))
            ->put(route('user.profile.update'), [
                'street' => '12 Rizal Street',
                'province' => 'Surigao del Sur',
                'city' => 'Dapa',
            ])
            ->assertRedirect(route('user.profile'))
            ->assertSessionHasErrors('city');

        $this->assertSame('Tandag', $user->fresh()->city);
        $this->assertSame('12 Rizal Street', $user->fresh()->street);
    }

    public function test_staff_and_scholar_registration_save_street_without_removing_existing_location(): void
    {
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-reg-location', 'Surigao del Norte');

        $this->post(route('register.staff.post'), [
            'full_name' => 'Street Staff',
            'scholarship_program_id' => $dapa->id,
            'scholarship_club_name' => 'Dapa Street Club',
            'email' => 'street-staff@example.com',
            'cellphone_number' => '09170000000',
            'street' => '45 Bonifacio Street',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $staff = User::query()->where('email', 'street-staff@example.com')->first();
        $this->assertNotNull($staff);
        $this->assertSame('45 Bonifacio Street', $staff->street);
        $this->assertSame('Dapa', $staff->city);
        $this->assertSame('Surigao del Norte', $staff->province);
        $this->assertSame('45 Bonifacio Street', $staff->scholarshipClub?->street);
        $this->assertSame('45 Bonifacio Street, Dapa, Surigao del Norte', $staff->scholarshipClubAddress());

        $staff->forceFill(['status' => User::STATUS_APPROVED])->save();

        $scholar = User::register([
            'full_name' => 'Existing Scholar',
            'scholar_id' => 'SCH-LOC-KEEP',
            'email' => 'existing-location@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $dapa->id,
            'scholarship_club_id' => $staff->scholarship_club_id,
            'province' => 'Surigao del Norte',
            'city' => 'Dapa',
            'street' => '9 Saved Street',
        ]);

        $this->actingAs($staff->fresh())
            ->put(route('staff.settings.club'), [
                'scholarship_club_name' => 'Dapa Street Club',
                'scholarship_program_id' => $dapa->id,
                'cellphone_number' => '09170000000',
                'street' => '88 New Street',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $staff->refresh();
        $scholar->refresh();
        $this->assertSame('88 New Street', $staff->street);
        $this->assertSame('88 New Street', $staff->scholarshipClub?->street);
        $this->assertSame('Dapa', $staff->city);
        $this->assertSame('9 Saved Street', $scholar->street);
        $this->assertSame('Dapa', $scholar->city);

        $this->actingAs($staff)
            ->get(route('staff.settings'))
            ->assertOk()
            ->assertSee('No./Street')
            ->assertSee('value="88 New Street"', false)
            ->assertSee('data-province-name="Surigao del Norte"', false)
            ->assertSee('data-province-name="Surigao del Sur"', false)
            ->assertSee('Dapa')
            ->assertDontSee('>Region<', false);
    }

    public function test_admin_location_filters_drop_region_and_keep_surigao_provinces(): void
    {
        $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-admin-location', null);
        $this->makeProgram('province', 'Surigao del Sur', 'Surigao del Sur', 'sds-admin-location', null);
        $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-admin-location', 'Surigao del Norte');
        $this->makeProgram('city_municipality', 'Manila', 'Manila', 'manila-admin-location', 'Metro Manila');

        $admin = User::register([
            'full_name' => 'Location Admin',
            'scholar_id' => 'ADMIN-LOC-001',
            'email' => 'location-admin@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>Surigao del Norte</option>', false)
            ->assertSee('>Surigao del Sur</option>', false)
            ->assertDontSee('>Manila</option>', false)
            ->assertDontSee('>Metro Manila</option>', false)
            ->assertDontSee('All Regions')
            ->assertDontSee('name="region"', false)
            ->assertDontSee('>Region<', false);
    }

    private function makeProgram(string $type, string $location, string $display, string $slug, ?string $province): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => $location,
            'location_type' => $type,
            'name' => $display,
            'display_name' => $display,
            'province_name' => $province,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }
}
