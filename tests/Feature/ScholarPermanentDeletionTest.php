<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\ScholarNotification;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScholarPermanentDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_permanent_delete_removes_scholar_data_and_allows_reregistration(): void
    {
        Storage::fake('public');

        [$staff, $scholar, $club, $program] = $this->makeApprovedScholarWithStaff();

        $email = $scholar->email;
        $scholarCode = $scholar->scholar_id;
        $scholarId = $scholar->id;

        $avatarPath = UploadedFile::fake()->image('avatar.jpg')->store("avatars/{$scholarId}", 'public');
        $scholar->forceFill(['avatar_path' => $avatarPath])->save();

        $docType = DocumentType::create([
            'name' => 'Certificate of Indigency',
            'slug' => 'certificate-of-indigency-delete',
            'required' => true,
            'scholarship_program_id' => $program->id,
        ]);

        $documentPath = UploadedFile::fake()->create('indigency.pdf', 120, 'application/pdf')
            ->store("documents/{$scholarId}", 'public');

        $document = Document::create([
            'user_id' => $scholarId,
            'document_type_id' => $docType->id,
            'academic_year_start' => 2026,
            'academic_year_end' => 2027,
            'file_path' => $documentPath,
            'original_name' => 'indigency.pdf',
            'status' => 'pending',
            'uploaded_at' => now(),
        ]);

        $event = Event::create([
            'title' => 'Community Clean-up',
            'description' => 'Service event',
            'location' => 'Surigao City',
            'starts_at' => now()->addDay()->setTime(8, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
            'service_hours' => 4,
            'organizer' => 'BSSA',
            'status' => 'published',
            'scholarship_program_id' => $program->id,
        ]);

        EventRegistration::create([
            'user_id' => $scholarId,
            'event_id' => $event->id,
        ]);

        $photoPath = UploadedFile::fake()->image('checkin.jpg')->store("attendance_photos/{$scholarId}", 'public');

        $attendance = Attendance::create([
            'user_id' => $scholarId,
            'event_id' => $event->id,
            'academic_year_start' => 2026,
            'academic_year_end' => 2027,
            'semester' => '1st Semester',
            'check_in' => now(),
            'hours_earned' => 4,
            'status' => Attendance::STATUS_PENDING,
            'photo_path' => $photoPath,
            'photo_original_name' => 'checkin.jpg',
            'photo_uploaded_at' => now(),
        ]);

        UserActivity::create([
            'user_id' => $scholarId,
            'type' => 'account',
            'description' => 'Joined an event',
        ]);

        ScholarNotification::create([
            'user_id' => $scholarId,
            'title' => 'Welcome',
            'body' => 'Hello',
            'category' => 'system',
        ]);

        $otherScholar = User::register([
            'full_name' => 'Other Scholar',
            'scholar_id' => 'SCH-OTHER-KEEP',
            'email' => 'other-scholar-keep@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($staff)
            ->from(route('staff.scholars.show', $scholar))
            ->delete(route('staff.scholars.destroy', $scholar))
            ->assertRedirect(route('staff.scholars'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $scholarId]);
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseMissing('users', ['scholar_id' => $scholarCode]);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseMissing('event_registrations', [
            'user_id' => $scholarId,
            'event_id' => $event->id,
        ]);
        $this->assertDatabaseMissing('user_activities', ['user_id' => $scholarId]);
        $this->assertDatabaseMissing('scholar_notifications', ['user_id' => $scholarId]);

        Storage::disk('public')->assertMissing($avatarPath);
        Storage::disk('public')->assertMissing($documentPath);
        Storage::disk('public')->assertMissing($photoPath);

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'email' => $staff->email,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $otherScholar->id,
            'email' => 'other-scholar-keep@example.com',
        ]);
        $this->assertDatabaseHas('scholarship_clubs', ['id' => $club->id]);
        $this->assertDatabaseHas('events', ['id' => $event->id]);

        $school = \App\Models\ScholarshipClubSchool::createForClub('Dapa National High School', $club->id);

        $this->post(route('register.post'), [
            'full_name' => 'Returning Scholar',
            'scholar_id' => $scholarCode,
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '2nd Year',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'scholar_id' => $scholarCode,
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_PENDING,
        ]);
    }

    public function test_pending_scholar_cannot_be_deleted_with_destroy_route(): void
    {
        [$staff, $scholar] = $this->makeApprovedScholarWithStaff();
        $scholar->forceFill(['status' => User::STATUS_PENDING])->save();

        $this->actingAs($staff)
            ->delete(route('staff.scholars.destroy', $scholar))
            ->assertRedirect(route('staff.scholars.show', $scholar))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $scholar->id]);
    }

    public function test_registration_purges_legacy_rejected_scholar_and_allows_reuse(): void
    {
        [$staff, $scholar, $club] = $this->makeApprovedScholarWithStaff();
        $email = 'legacy-rejected@example.com';
        $scholarCode = 'SCH-LEGACY-REJECT';

        $scholar->forceFill([
            'email' => $email,
            'scholar_id' => $scholarCode,
            'status' => User::STATUS_REJECTED,
        ])->save();

        $school = \App\Models\ScholarshipClubSchool::createForClub('Legacy Reject School', $club->id);

        $this->post(route('register.post'), [
            'full_name' => 'Legacy Returning Scholar',
            'scholar_id' => $scholarCode,
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '1st Year',
            'email' => strtoupper($email),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', [
            'id' => $scholar->id,
            'status' => User::STATUS_REJECTED,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'scholar_id' => $scholarCode,
            'status' => User::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('users', ['email' => $staff->email]);
    }

    public function test_active_scholar_email_still_blocks_registration(): void
    {
        [, $scholar, $club] = $this->makeApprovedScholarWithStaff();
        $school = \App\Models\ScholarshipClubSchool::createForClub('Active Block School', $club->id);

        $this->post(route('register.post'), [
            'full_name' => 'Duplicate Email Scholar',
            'scholar_id' => 'SCH-DUP-EMAIL-001',
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '1st Year',
            'email' => $scholar->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email' => 'An account with this email already exists.']);
    }

    /**
     * @return array{0: User, 1: User, 2: ScholarshipClub, 3: ScholarshipProgram}
     */
    private function makeApprovedScholarWithStaff(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-scholar-delete',
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Dapa Delete Club', $program->id);

        $staff = User::register([
            'full_name' => 'Delete Staff',
            'scholar_id' => 'STAFF-DELETE-001',
            'email' => 'delete-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);
        $club->forceFill(['created_by' => $staff->id])->save();

        $scholar = User::register([
            'full_name' => 'Delete Me Scholar',
            'scholar_id' => 'SCH-DELETE-REUSE',
            'email' => 'delete-me-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        return [$staff, $scholar, $club, $program];
    }
}
