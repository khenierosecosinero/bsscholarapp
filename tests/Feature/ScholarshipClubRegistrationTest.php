<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipClubRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProgram(string $type, string $location, string $display, string $slug, ?string $province = null): ScholarshipProgram
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

    private function makeClub(ScholarshipProgram $program, string $name, ?User $staff = null): ScholarshipClub
    {
        $location = $program->registrationLocation();

        return ScholarshipClub::create([
            'name' => $name,
            'city' => $location['city'],
            'province' => $location['province'],
            'scholarship_program_id' => $program->id,
            'created_by' => $staff?->id,
            'is_active' => true,
        ]);
    }

    public function test_register_page_shows_scholarship_club_and_readonly_address(): void
    {
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-page', 'Surigao del Norte');
        $this->makeClub($city, 'Dapa Scholars Club');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Scholarship Club')
            ->assertSee('name="scholarship_club_id"', false)
            ->assertSee('Dapa Scholars Club')
            ->assertSee('data-city="Dapa"', false)
            ->assertSee('data-province="Surigao del Norte"', false)
            ->assertSee('name="scholarship_club_school_id"', false)
            ->assertSee('name="course_year_level"', false)
            ->assertSee('placeholder="Bachelor of Science in ..."', false)
            ->assertDontSee('<select class="form-input form-select" id="course_year_level"', false)
            ->assertDontSee('Create your scholar account to get started. Select the Scholarship Club you want to register under. Its Province, Municipality/City, and School/University options come from that club.')
            ->assertDontSee('Type the complete official course name. Do not use initials such as BSICT, BSCE, or BSIS.')
            ->assertDontSee('Course and Year Level are saved to your account and shown in User Settings.')
            ->assertDontSee('value="BSICT"', false)

            ->assertSee('1st Year')
            ->assertSee('2nd Year')
            ->assertSee('3rd Year')
            ->assertSee('4th Year')
            ->assertSee('name="year_level"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('name="scholarship_program_id"', false);
    }

    public function test_staff_register_page_does_not_show_scholar_staff_number(): void
    {
        $this->get(route('register.staff'))
            ->assertOk()
            ->assertDontSee('Scholar Staff Number')
            ->assertDontSee('name="scholar_id"', false)
            ->assertSee('placeholder="Contact Number"', false)
            ->assertSee('name="cellphone_number"', false)
            ->assertDontSee('name="staff_started_year"', false)
            ->assertDontSee('Started Year')
            ->assertDontSee('Select Started Year');
    }

    public function test_staff_registration_saves_typed_club_name_and_address(): void
    {
        $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-staff-reg');
        $program = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-staff-reg', 'Surigao del Norte');

        $this->post(route('register.staff.post'), [
            'full_name' => 'Club Staff',
            'scholarship_program_id' => $program->id,
            'scholarship_club_name' => 'Dapa Scholars Club',
            'email' => 'club-staff-reg@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $staff = User::query()->where('email', 'club-staff-reg@example.com')->first();

        $this->assertNotNull($staff);
        $this->assertSame(User::ROLE_SCHOLAR_STAFF, $staff->role);
        $this->assertNotSame('', $staff->scholar_id);
        $this->assertStringStartsWith('STAFF-', $staff->scholar_id);
        $this->assertSame('09171234567', $staff->contactNumber());
        $this->assertSame($program->id, $staff->scholarship_program_id);
        $this->assertSame('Dapa', $staff->city);
        $this->assertSame('Surigao del Norte', $staff->province);
        $this->assertNotNull($staff->scholarship_club_id);
        $this->assertSame('09171234567', $staff->cellphone_number);
        $this->assertSame('Dapa Scholars Club', $staff->scholarshipClubName());
        $this->assertSame('Dapa, Surigao del Norte', $staff->scholarshipClubAddress());
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $staff->scholarship_club_id,
            'name' => 'Dapa Scholars Club',
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'created_by' => $staff->id,
        ]);
    }

    public function test_staff_registration_rejects_a_province_program_as_club_address(): void
    {
        $province = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-staff-reject');

        $this->post(route('register.staff.post'), [
            'full_name' => 'Club Staff',
            'scholarship_program_id' => $province->id,
            'scholarship_club_name' => 'Province Club',
            'email' => 'province-club@example.com',
            'cellphone_number' => '09170000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('scholarship_program_id');

        $this->assertDatabaseMissing('users', ['email' => 'province-club@example.com']);
        $this->assertDatabaseMissing('scholarship_clubs', ['name' => 'Province Club']);
    }

    public function test_staff_registration_rejects_duplicate_club_names_case_insensitively(): void
    {
        $cityA = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-dup-a', 'Surigao del Norte');
        $cityB = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-club-dup-b', 'Surigao del Sur');

        $existingClub = ScholarshipClub::createForProgram('Batang Surigaonon Scholars Club', $cityA->id);
        $owner = User::register([
            'full_name' => 'Existing Club Staff',
            'scholar_id' => 'STAFF-CLUB-DUP-OWNER',
            'email' => 'existing-club-owner@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $cityA->id,
            'scholarship_club_id' => $existingClub->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);
        $existingClub->forceFill(['created_by' => $owner->id])->save();

        $this->post(route('register.staff.post'), [
            'full_name' => 'Duplicate Club Staff',
            'scholarship_program_id' => $cityB->id,
            'scholarship_club_name' => '  batang surigaonon scholars club  ',
            'email' => 'duplicate-club-staff@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertSessionHasErrors([
                'scholarship_club_name' => ScholarshipClub::DUPLICATE_NAME_MESSAGE,
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'duplicate-club-staff@example.com']);
        $this->assertSame(1, ScholarshipClub::query()->where('name_key', 'batang surigaonon scholars club')->count());
    }

    public function test_staff_settings_rejects_rename_to_an_existing_club_name(): void
    {
        $cityA = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-rename-dup', 'Surigao del Norte');
        $cityB = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-rename-dup', 'Surigao del Sur');
        $clubA = ScholarshipClub::createForProgram('Dapa Scholars Club', $cityA->id);
        $clubB = ScholarshipClub::createForProgram('Tandag Scholars Club', $cityB->id);

        $staff = User::register([
            'full_name' => 'Rename Staff',
            'scholar_id' => 'STAFF-RENAME-DUP',
            'email' => 'rename-dup-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $cityA->id,
            'scholarship_club_id' => $clubA->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);
        $clubA->forceFill(['created_by' => $staff->id])->save();

        $otherStaff = User::register([
            'full_name' => 'Other Rename Staff',
            'scholar_id' => 'STAFF-RENAME-OTHER',
            'email' => 'rename-other-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $cityB->id,
            'scholarship_club_id' => $clubB->id,
            'city' => 'Tandag',
            'province' => 'Surigao del Sur',
        ]);
        $clubB->forceFill(['created_by' => $otherStaff->id])->save();

        $this->actingAs($staff)
            ->from(route('staff.settings'))
            ->put(route('staff.settings.club'), [
                'scholarship_club_name' => 'TANDAG SCHOLARS CLUB',
                'scholarship_program_id' => $cityA->id,
                'cellphone_number' => '09179876543',
            ])
            ->assertRedirect(route('staff.settings'))
            ->assertSessionHasErrors([
                'scholarship_club_name' => ScholarshipClub::DUPLICATE_NAME_MESSAGE,
            ]);

        $this->assertSame('Dapa Scholars Club', $clubA->fresh()->name);
        $this->assertSame('Tandag Scholars Club', $clubB->fresh()->name);
    }

    public function test_permanent_staff_deletion_releases_club_name_for_reregistration(): void
    {
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-release', 'Surigao del Norte');
        $otherCity = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-club-keep', 'Surigao del Sur');
        $keptClub = ScholarshipClub::createForProgram('Tandag Keepers Club', $otherCity->id);

        $this->post(route('register.staff.post'), [
            'full_name' => 'Release Club Staff',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Batang Surigaonon Scholars Club',
            'email' => 'release-club-staff@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $staff = User::query()->where('email', 'release-club-staff@example.com')->first();
        $this->assertNotNull($staff);
        $clubId = (int) $staff->scholarship_club_id;
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $clubId,
            'name_key' => 'batang surigaonon scholars club',
        ]);

        $staff->forceFill(['status' => User::STATUS_APPROVED])->save();

        $scholar = User::register([
            'full_name' => 'Club Scholar Kept',
            'scholar_id' => 'SCH-CLUB-RELEASE',
            'email' => 'club-scholar-release@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $clubId,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $keptStaff = User::register([
            'full_name' => 'Kept Staff',
            'scholar_id' => 'STAFF-CLUB-KEEP',
            'email' => 'kept-club-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $otherCity->id,
            'scholarship_club_id' => $keptClub->id,
            'city' => 'Tandag',
            'province' => 'Surigao del Sur',
        ]);

        $admin = User::register([
            'full_name' => 'Release Admin',
            'scholar_id' => 'ADMIN-CLUB-RELEASE',
            'email' => 'admin-club-release@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->delete(route('admin.staff.delete', $staff), ['location' => 'all'])
            ->assertRedirect(route('admin.staff', ['location' => 'all']));

        $this->assertDatabaseMissing('users', ['email' => 'release-club-staff@example.com']);
        $this->assertDatabaseMissing('scholarship_clubs', ['id' => $clubId]);
        $this->assertDatabaseHas('users', [
            'email' => 'club-scholar-release@example.com',
            'scholarship_club_id' => null,
        ]);
        $this->assertDatabaseHas('users', ['email' => 'kept-club-staff@example.com']);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $keptClub->id,
            'name' => 'Tandag Keepers Club',
        ]);
        $this->assertSame($keptClub->id, $keptStaff->fresh()->scholarship_club_id);
        $this->assertNotNull($scholar->fresh());

        $this->post(route('register.staff.post'), [
            'full_name' => 'Reregister Club Staff',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'batang surigaonon scholars club',
            'email' => 'reregister-club-staff@example.com',
            'cellphone_number' => '09179876543',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', ['email' => 'reregister-club-staff@example.com']);
        $this->assertDatabaseHas('scholarship_clubs', [
            'name' => 'batang surigaonon scholars club',
            'name_key' => 'batang surigaonon scholars club',
        ]);
    }

    public function test_orphaned_club_without_staff_does_not_block_reregistration(): void
    {
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-orphan', 'Surigao del Norte');

        $orphan = ScholarshipClub::create([
            'name' => 'Batang Surigaonon Scholars Club',
            'name_key' => 'batang surigaonon scholars club',
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $city->id,
            'created_by' => null,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('scholarship_clubs', ['id' => $orphan->id]);

        $this->post(route('register.staff.post'), [
            'full_name' => 'Orphan Reclaim Staff',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Batang Surigaonon Scholars Club',
            'email' => 'orphan-reclaim-staff@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('scholarship_clubs', ['id' => $orphan->id]);
        $this->assertDatabaseHas('users', ['email' => 'orphan-reclaim-staff@example.com']);
        $this->assertSame(
            1,
            ScholarshipClub::query()->where('name_key', 'batang surigaonon scholars club')->count()
        );
    }

    public function test_deactivating_staff_does_not_release_club_name(): void
    {
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-inactive', 'Surigao del Norte');

        $this->post(route('register.staff.post'), [
            'full_name' => 'Inactive Club Staff',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Inactive Holders Club',
            'email' => 'inactive-club-staff@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $staff = User::query()->where('email', 'inactive-club-staff@example.com')->firstOrFail();
        $staff->forceFill(['status' => User::STATUS_APPROVED])->save();
        $clubId = (int) $staff->scholarship_club_id;

        $admin = User::register([
            'full_name' => 'Inactive Admin',
            'scholar_id' => 'ADMIN-CLUB-INACTIVE',
            'email' => 'admin-club-inactive@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->post(route('admin.staff.deactivate', $staff), ['location' => 'all'])
            ->assertRedirect(route('admin.staff', ['location' => 'all']));

        $this->assertSame(User::STATUS_INACTIVE, $staff->fresh()->status);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $clubId,
            'name_key' => 'inactive holders club',
        ]);

        $this->post(route('register.staff.post'), [
            'full_name' => 'Should Fail Staff',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Inactive Holders Club',
            'email' => 'should-fail-club-staff@example.com',
            'cellphone_number' => '09170000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors([
            'scholarship_club_name' => ScholarshipClub::DUPLICATE_NAME_MESSAGE,
        ]);
    }

    public function test_scholar_registration_saves_selected_club_and_its_address(): void
    {
        $province = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte Club', 'sdn-club-save');
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-save', 'Surigao del Norte');
        $club = $this->makeClub($city, 'Dapa Scholars Club');
        $school = ScholarshipClubSchool::createForClub('Dapa National High School', $club->id);

        $this->post(route('register.post'), [
            'full_name' => 'Club Scholar',
            'scholar_id' => 'SNS-CLUB-001',
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '2nd Year',
            'email' => 'club-scholar@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas(
                'success',
                'You have successfully created an account! Your account is currently pending approval from the authorized scholar staff. You can log in to view your restricted dashboard while you wait for approval.'
            );

        $scholar = User::query()->where('email', 'club-scholar@example.com')->first();

        $this->assertNotNull($scholar);
        $this->assertSame(User::ROLE_SCHOLAR, $scholar->role);
        $this->assertSame(User::STATUS_PENDING, $scholar->status);
        $this->assertTrue($scholar->canLogin());
        $this->assertFalse($scholar->hasScholarPortalAccess());
        $this->assertSame($city->id, $scholar->scholarship_program_id);
        $this->assertSame($club->id, $scholar->scholarship_club_id);
        $this->assertSame('Dapa', $scholar->city);
        $this->assertSame('Surigao del Norte', $scholar->province);
        $this->assertSame($school->id, $scholar->scholarship_club_school_id);
        $this->assertSame('Dapa National High School', $scholar->school_university);
        $this->assertSame('Bachelor of Science in Information Technology', $scholar->course_year_level);
        $this->assertSame('2nd Year', $scholar->year_level);
        $this->assertSame('Dapa Scholars Club', $scholar->scholarshipClubName());
        $this->assertSame('Dapa', $scholar->scholarshipClubCity());
        $this->assertSame('Surigao del Norte', $scholar->scholarshipClubProvince());
        $this->assertNotSame($province->id, $scholar->scholarship_program_id);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('You can log in to view your restricted dashboard while you wait for approval.');

        $this->from(route('login'))
            ->post(route('login.post'), [
                'email' => 'club-scholar@example.com',
                'password' => 'password123',
            ])
            ->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($scholar->fresh());

        $this->get(route('user.events'))
            ->assertRedirect(route('user.dashboard'));

        auth()->logout();
        $scholar->forceFill(['status' => User::STATUS_APPROVED])->save();

        $this->assertTrue($scholar->fresh()->canLogin());
        $this->assertTrue($scholar->fresh()->hasScholarPortalAccess());

        $this->post(route('login.post'), [
            'email' => 'club-scholar@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($scholar->fresh());
        $this->get(route('user.events'))->assertOk();
    }

    public function test_scholar_registration_requires_a_scholarship_club(): void
    {
        $this->post(route('register.post'), [
            'full_name' => 'No Club Scholar',
            'scholar_id' => 'SNS-CLUB-002',
            'email' => 'no-club@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('scholarship_club_id');

        $this->assertDatabaseMissing('users', ['email' => 'no-club@example.com']);
    }

    public function test_scholar_registration_rejects_course_initials(): void
    {
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-course', 'Surigao del Norte');
        $club = $this->makeClub($city, 'Dapa Course Club');
        $school = ScholarshipClubSchool::createForClub('Dapa National High School', $club->id);

        foreach (['BSICT', 'BSCE', 'BSIS'] as $index => $initials) {
            $this->post(route('register.post'), [
                'full_name' => 'Initials Scholar',
                'scholar_id' => 'SNS-CLUB-COURSE-00'.$index,
                'scholarship_club_id' => $club->id,
                'scholarship_club_school_id' => $school->id,
                'course_year_level' => $initials,
                'email' => 'course-initials-'.$index.'@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertSessionHasErrors('course_year_level');

            $this->assertDatabaseMissing('users', ['email' => 'course-initials-'.$index.'@example.com']);
        }
    }

    public function test_profile_shows_registered_course_and_year_level(): void
    {
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-profile-course', 'Surigao del Norte');
        $club = $this->makeClub($city, 'Dapa Profile Club');
        $school = ScholarshipClubSchool::createForClub('Dapa National High School', $club->id);

        $this->post(route('register.post'), [
            'full_name' => 'Profile Course Scholar',
            'scholar_id' => 'SNS-CLUB-COURSE-002',
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '2nd Year',
            'email' => 'profile-course@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $scholar = User::query()->where('email', 'profile-course@example.com')->first();
        $this->assertNotNull($scholar);
        $scholar->status = User::STATUS_APPROVED;
        $scholar->save();

        $this->actingAs($scholar)
            ->get(route('user.profile'))
            ->assertOk()
            ->assertSee('Bachelor of Science in Information Technology')
            ->assertSee('2nd Year')
            ->assertSee('Dapa National High School')
            ->assertDontSee('BSICT')
            ->assertSee('name="course_year_level"', false)
            ->assertSee('name="year_level"', false)
            ->assertSee('name="scholarship_club_school_id"', false)
            ->assertDontSee('<select class="form-select" id="course_year_level"', false);

        $this->actingAs($scholar)
            ->from(route('user.profile'))
            ->put(route('user.profile.update'), [
                'full_name' => 'Profile Course Scholar',
                'course_year_level' => 'BSICT',
                'year_level' => '5th Year',
                'scholarship_club_school_id' => $school->id,
            ])
            ->assertRedirect(route('user.profile'))
            ->assertSessionHasErrors(['course_year_level', 'year_level']);

        $scholar->refresh();
        $this->assertSame('Bachelor of Science in Information Technology', $scholar->course_year_level);
        $this->assertSame('2nd Year', $scholar->year_level);
        $this->assertSame($school->id, $scholar->scholarship_club_school_id);
    }

    public function test_staff_settings_updates_club_name_address_and_contact(): void
    {
        $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-club-settings');
        $this->makeProgram('province', 'Surigao del Sur', 'Surigao del Sur', 'sds-club-settings');
        $program = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-settings', 'Surigao del Norte');
        $nextCity = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-club-settings', 'Surigao del Sur');
        $club = $this->makeClub($program, 'Dapa Scholars Club');

        $staff = User::register([
            'full_name' => 'Club Staff',
            'scholar_id' => 'STAFF-CLUB-001',
            'email' => 'club-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $club->created_by = $staff->id;
        $club->save();

        $this->assertSame('', $staff->contactNumber());

        $scholar = User::register([
            'full_name' => 'Club Scholar',
            'scholar_id' => 'SCH-CLUB-SETTINGS',
            'email' => 'club-scholar-settings@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.settings'))
            ->assertOk()
            ->assertSee('value="Dapa Scholars Club"', false)
            ->assertSee('name="scholarship_program_id"', false)
            ->assertSee('name="cellphone_number"', false)
            ->assertDontSee('Scholar Staff Number')
            ->assertDontSee('value="STAFF-CLUB-001"', false)
            ->assertSee('Scholarship Club linked to this staff account');

        $this->actingAs($staff)
            ->put(route('staff.settings.club'), [
                'scholarship_club_name' => 'Tandag Youth Scholars',
                'scholarship_program_id' => $nextCity->id,
                'cellphone_number' => '09179876543',
            ])
            ->assertRedirect();

        $staff = $staff->fresh(['scholarshipClub']);
        $this->assertSame('Tandag Youth Scholars', $staff->scholarshipClubName());
        $this->assertSame('Tandag, Surigao del Sur', $staff->scholarshipClubAddress());
        $this->assertSame($nextCity->id, $staff->scholarship_program_id);
        $this->assertSame('Tandag', $staff->city);
        $this->assertSame('Surigao del Sur', $staff->province);
        $this->assertSame('09179876543', $staff->cellphone_number);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $club->id,
            'name' => 'Tandag Youth Scholars',
            'city' => 'Tandag',
            'province' => 'Surigao del Sur',
            'scholarship_program_id' => $nextCity->id,
        ]);
        $this->assertSame($nextCity->id, $scholar->fresh()->scholarship_program_id);
        $this->assertSame($club->id, $scholar->fresh()->scholarship_club_id);
    }

    public function test_staff_only_manages_scholars_in_their_scholarship_club(): void
    {
        $programA = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-scope', 'Surigao del Norte');
        $programB = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-club-scope', 'Surigao del Sur');
        $clubA = $this->makeClub($programA, 'Dapa Scholars Club');
        $clubB = $this->makeClub($programB, 'Tandag Scholars Club');

        $staffA = User::register([
            'full_name' => 'Dapa Staff',
            'scholar_id' => 'STAFF-CLUB-A',
            'email' => 'dapa-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $programA->id,
            'scholarship_club_id' => $clubA->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $scholarA = User::register([
            'full_name' => 'Dapa Scholar',
            'scholar_id' => 'SCH-CLUB-A',
            'email' => 'dapa-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $programA->id,
            'scholarship_club_id' => $clubA->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $scholarB = User::register([
            'full_name' => 'Tandag Scholar',
            'scholar_id' => 'SCH-CLUB-B',
            'email' => 'tandag-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $programB->id,
            'scholarship_club_id' => $clubB->id,
            'city' => 'Tandag',
            'province' => 'Surigao del Sur',
        ]);

        $this->assertTrue($staffA->canManageScholar($scholarA));
        $this->assertFalse($staffA->canManageScholar($scholarB));

        $this->actingAs($staffA)
            ->get(route('staff.scholars'))
            ->assertOk()
            ->assertSee('Dapa Scholar')
            ->assertSee('Dapa')
            ->assertSee('Surigao del Norte')
            ->assertDontSee('Tandag Scholar');

        $this->actingAs($staffA)
            ->get(route('staff.scholars.show', $scholarB))
            ->assertForbidden();
    }
}
