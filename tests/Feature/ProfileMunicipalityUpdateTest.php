<?php

namespace Tests\Feature;

use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileMunicipalityUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_scholar_can_select_and_save_municipality_without_changing_program(): void
    {
        $province = ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'surigao-del-norte-test',
            'is_active' => true,
        ]);

        ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa Scholarship Program',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-test',
            'is_active' => true,
        ]);

        $user = User::register([
            'full_name' => 'Test Scholar',
            'scholar_id' => 'SNS-TEST-001',
            'email' => 'profile-city-test@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $province->id,
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($user)
            ->get(route('user.profile'))
            ->assertOk()
            ->assertSee('name="city"', false)
            ->assertSee('Dapa')
            ->assertSee('Select municipality or city');

        $this->actingAs($user)
            ->put(route('user.profile.update'), [
                'full_name' => 'Test Scholar',
                'city' => 'Dapa',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertSame('Dapa', $user->city);
        $this->assertSame($province->id, $user->scholarship_program_id);
        $this->assertSame('Surigao del Norte', $user->province);
    }

    public function test_profile_rejects_a_city_outside_the_allowed_list(): void
    {
        $province = ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'surigao-del-norte-reject',
            'is_active' => true,
        ]);

        ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa Scholarship Program',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-reject',
            'is_active' => true,
        ]);

        $user = User::register([
            'full_name' => 'Test Scholar',
            'scholar_id' => 'SNS-TEST-002',
            'email' => 'profile-city-reject@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $province->id,
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($user)
            ->from(route('user.profile'))
            ->put(route('user.profile.update'), [
                'full_name' => 'Test Scholar',
                'city' => 'Not A Real City',
            ])
            ->assertRedirect(route('user.profile'))
            ->assertSessionHasErrors('city');

        $this->assertNull($user->fresh()->city);
        $this->assertSame($province->id, $user->fresh()->scholarship_program_id);
    }
}
