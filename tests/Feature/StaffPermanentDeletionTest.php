<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPermanentDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_delete_removes_staff_releases_club_name_and_allows_reregistration(): void
    {
        $city = $this->makeCity('dapa-staff-delete');
        $otherCity = $this->makeCity('tandag-staff-keep', 'Tandag', 'Surigao del Sur');
        $keptClub = ScholarshipClub::createForProgram('Tandag Keepers Club', $otherCity->id);

        $this->post(route('register.staff.post'), [
            'full_name' => 'Delete Staff Owner',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Reusable Staff Club',
            'email' => 'delete-staff-owner@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $staff = User::query()->where('email', 'delete-staff-owner@example.com')->firstOrFail();
        $staff->forceFill(['status' => User::STATUS_APPROVED])->save();
        $clubId = (int) $staff->scholarship_club_id;
        $clubName = 'Reusable Staff Club';

        $scholar = User::register([
            'full_name' => 'Club Scholar Kept',
            'scholar_id' => 'SCH-STAFF-DEL-KEEP',
            'email' => 'club-scholar-staff-del@example.com',
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
            'scholar_id' => 'STAFF-KEEP-OTHER',
            'email' => 'kept-other-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $otherCity->id,
            'scholarship_club_id' => $keptClub->id,
            'city' => 'Tandag',
            'province' => 'Surigao del Sur',
        ]);
        $keptClub->forceFill(['created_by' => $keptStaff->id])->save();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->delete(route('admin.staff.delete', $staff), ['location' => 'all'])
            ->assertRedirect(route('admin.staff', ['location' => 'all']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['email' => 'delete-staff-owner@example.com']);
        $this->assertDatabaseMissing('scholarship_clubs', ['id' => $clubId]);
        $this->assertDatabaseHas('users', [
            'email' => 'club-scholar-staff-del@example.com',
            'scholarship_club_id' => null,
        ]);
        $this->assertDatabaseHas('users', ['email' => 'kept-other-staff@example.com']);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $keptClub->id,
            'name' => 'Tandag Keepers Club',
        ]);
        $this->assertNotNull($scholar->fresh());

        $this->post(route('register.staff.post'), [
            'full_name' => 'Returning Staff Owner',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => $clubName,
            'email' => 'delete-staff-owner@example.com',
            'cellphone_number' => '09179876543',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'delete-staff-owner@example.com',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('scholarship_clubs', [
            'name' => $clubName,
            'name_key' => 'reusable staff club',
        ]);
    }

    public function test_admin_reject_permanently_removes_pending_staff_and_allows_reregistration(): void
    {
        $city = $this->makeCity('dapa-staff-reject');

        $this->post(route('register.staff.post'), [
            'full_name' => 'Pending Reject Staff',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Pending Reject Club',
            'email' => 'pending-reject-staff@example.com',
            'cellphone_number' => '09170001111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $staff = User::query()->where('email', 'pending-reject-staff@example.com')->firstOrFail();
        $this->assertSame(User::STATUS_PENDING, $staff->status);
        $clubId = (int) $staff->scholarship_club_id;

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.staff.reject', $staff), ['location' => 'all'])
            ->assertRedirect(route('admin.staff', ['location' => 'all']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['email' => 'pending-reject-staff@example.com']);
        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseMissing('scholarship_clubs', ['id' => $clubId]);
        $this->assertFalse(ScholarshipClub::nameIsOwnedByStaff('pending reject club'));

        $this->post(route('register.staff.post'), [
            'full_name' => 'Pending Reject Staff Again',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Pending Reject Club',
            'email' => 'pending-reject-staff@example.com',
            'cellphone_number' => '09170002222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'pending-reject-staff@example.com',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('scholarship_clubs', [
            'name' => 'Pending Reject Club',
            'name_key' => 'pending reject club',
        ]);
    }

    public function test_reject_does_not_release_club_still_owned_by_another_staff(): void
    {
        $city = $this->makeCity('dapa-shared-club-reject');
        $club = ScholarshipClub::createForProgram('Shared Staff Club', $city->id);

        $owner = User::register([
            'full_name' => 'Shared Owner Staff',
            'scholar_id' => 'STAFF-SHARED-OWNER',
            'email' => 'shared-owner-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);
        $club->forceFill(['created_by' => $owner->id])->save();

        $pending = User::register([
            'full_name' => 'Shared Pending Staff',
            'scholar_id' => 'STAFF-SHARED-PENDING',
            'email' => 'shared-pending-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_PENDING,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.staff.reject', $pending), ['location' => 'all'])
            ->assertRedirect(route('admin.staff', ['location' => 'all']));

        $this->assertDatabaseMissing('users', ['email' => 'shared-pending-staff@example.com']);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $club->id,
            'name' => 'Shared Staff Club',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'shared-owner-staff@example.com',
            'scholarship_club_id' => $club->id,
        ]);

        $this->post(route('register.staff.post'), [
            'full_name' => 'Should Fail Shared Club',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Shared Staff Club',
            'email' => 'shared-pending-staff@example.com',
            'cellphone_number' => '09170003333',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors([
            'scholarship_club_name' => ScholarshipClub::DUPLICATE_NAME_MESSAGE,
        ]);
    }

    private function makeCity(string $slug, string $city = 'Dapa', string $province = 'Surigao del Norte'): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => $city,
            'location_type' => 'city_municipality',
            'name' => $city.', '.$province,
            'display_name' => $city.', '.$province,
            'province_name' => $province,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'Staff Delete Admin',
            'scholar_id' => 'ADMIN-STAFF-DEL-001',
            'email' => 'admin-staff-del@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }
}
