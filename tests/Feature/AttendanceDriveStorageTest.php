<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Services\AttendanceDriveStorageService;
use App\Services\GoogleDriveService;
use App\Services\ScholarService;
use Google\Service\Drive\DriveFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class AttendanceDriveStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_approval_uploads_the_photo_into_the_bssa_attendance_tree(): void
    {
        Storage::fake('public');

        [$staff, $attendance] = $this->makePendingAttendance();
        $uploaded = $this->driveFile('att-file-1');

        $this->mockConnectedDrive(function (MockInterface $drive) use ($attendance, $uploaded) {
            $drive->shouldReceive('getOrCreateAttendanceEventFolder')
                ->once()
                ->withArgs(function (string $year, string $code, string $name, int $eventId, string $title) use ($attendance) {
                    return $year === '2026–2027'
                        && $code === 'BSSA-2026-001'
                        && $name === 'Juan Dela Cruz'
                        && $eventId === $attendance->event_id
                        && $title === 'Community Clean-Up Drive';
                })
                ->andReturn('event-folder-1');
            $drive->shouldReceive('findChildFile')
                ->once()
                ->with('BSSA-2026-001 - Juan Dela Cruz - Community Clean-Up Drive.jpg', 'event-folder-1')
                ->andReturn(null);
            $drive->shouldReceive('uploadRaw')
                ->once()
                ->withArgs(function (string $contents, string $folderId, string $name) {
                    return $contents === 'verified-photo'
                        && $folderId === 'event-folder-1'
                        && $name === 'BSSA-2026-001 - Juan Dela Cruz - Community Clean-Up Drive.jpg';
                })
                ->andReturn($uploaded);
            $drive->shouldReceive('getOrCreateRootFolder')->never();
            $drive->shouldReceive('ensureScholarFolder')->never();
        });

        $this->actingAs($staff)
            ->from(route('staff.events.show', $attendance->event))
            ->post(route('staff.attendances.approve', $attendance))
            ->assertRedirect(route('staff.events.show', $attendance->event));

        $attendance->refresh();

        $this->assertSame(Attendance::STATUS_APPROVED, $attendance->status);
        $this->assertSame('event-folder-1', $attendance->google_drive_folder_id);
        $this->assertSame('att-file-1', $attendance->google_drive_file_id);
        $this->assertSame('https://drive.google.com/file/d/att-file-1/view', $attendance->google_drive_web_link);
        $this->assertEquals(8, (float) $attendance->hours_earned);
    }

    public function test_pending_and_rejected_attendance_photos_are_not_uploaded(): void
    {
        Storage::fake('public');

        [, $pending] = $this->makePendingAttendance('pending');
        [, $rejected] = $this->makePendingAttendance('rejected', [
            'scholar_id' => 'BSSA-2026-002',
            'full_name' => 'Maria Santos',
            'email' => 'maria.santos@example.com',
        ]);
        $rejected->update(['status' => Attendance::STATUS_REJECTED]);

        $this->mockConnectedDrive(function (MockInterface $drive) {
            $drive->shouldReceive('getOrCreateAttendanceEventFolder')->never();
            $drive->shouldReceive('uploadRaw')->never();
        });

        $storage = app(AttendanceDriveStorageService::class);
        $storage->storeApprovedPhoto($pending->fresh(['user', 'event']));
        $storage->storeApprovedPhoto($rejected->fresh(['user', 'event']));

        $this->assertNull($pending->fresh()->google_drive_file_id);
        $this->assertNull($rejected->fresh()->google_drive_file_id);
    }

    public function test_approval_still_credits_hours_when_google_drive_is_disconnected(): void
    {
        Storage::fake('public');

        [$staff, $attendance] = $this->makePendingAttendance();

        $this->mock(GoogleDriveService::class, function (MockInterface $drive) {
            $drive->shouldReceive('isConnected')->andReturn(false);
            $drive->shouldReceive('sanitizeDriveName')->never();
            $drive->shouldReceive('uploadRaw')->never();
        });

        $this->actingAs($staff)
            ->post(route('staff.attendances.approve', $attendance))
            ->assertRedirect();

        $attendance->refresh();

        $this->assertSame(Attendance::STATUS_APPROVED, $attendance->status);
        $this->assertEquals(8, (float) $attendance->hours_earned);
        $this->assertNull($attendance->google_drive_file_id);
        $this->assertNull($attendance->google_drive_folder_id);
    }

    public function test_the_same_verified_attendance_record_is_not_uploaded_twice(): void
    {
        Storage::fake('public');

        [, $attendance] = $this->makePendingAttendance();
        $attendance->update([
            'status' => Attendance::STATUS_APPROVED,
            'hours_earned' => 8,
            'google_drive_folder_id' => 'event-folder-1',
            'google_drive_file_id' => 'att-file-1',
            'google_drive_web_link' => 'https://drive.google.com/file/d/att-file-1/view',
        ]);

        $this->mockConnectedDrive(function (MockInterface $drive) {
            $drive->shouldReceive('getOrCreateAttendanceEventFolder')->never();
            $drive->shouldReceive('uploadRaw')->never();
        });

        app(AttendanceDriveStorageService::class)->storeApprovedPhoto($attendance->fresh(['user', 'event']));
        app(ScholarService::class)->confirmParticipation($attendance->fresh(['user', 'event']));

        $attendance->refresh();
        $this->assertSame('att-file-1', $attendance->google_drive_file_id);
    }

    public function test_an_existing_drive_file_with_the_same_name_is_reused(): void
    {
        Storage::fake('public');

        [, $attendance] = $this->makePendingAttendance();
        $attendance->update(['status' => Attendance::STATUS_APPROVED, 'hours_earned' => 8]);
        $existing = $this->driveFile('existing-file');

        $this->mockConnectedDrive(function (MockInterface $drive) use ($existing) {
            $drive->shouldReceive('getOrCreateAttendanceEventFolder')->once()->andReturn('event-folder-1');
            $drive->shouldReceive('findChildFile')->once()->andReturn($existing);
            $drive->shouldReceive('uploadRaw')->never();
        });

        app(AttendanceDriveStorageService::class)->storeApprovedPhoto($attendance->fresh(['user', 'event']));

        $attendance->refresh();
        $this->assertSame('event-folder-1', $attendance->google_drive_folder_id);
        $this->assertSame('existing-file', $attendance->google_drive_file_id);
    }

    public function test_adding_an_academic_year_creates_attendance_year_folders_not_document_folders(): void
    {
        $admin = User::register([
            'full_name' => 'Drive Admin',
            'scholar_id' => 'ADMIN-DRIVE-001',
            'email' => 'admin-drive@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        $academic = app(AcademicSettingsService::class);
        $current = $academic->current();
        $synced = [];

        $this->mock(GoogleDriveService::class, function (MockInterface $drive) use (&$synced) {
            $drive->shouldReceive('isConnected')->andReturn(true);
            $drive->shouldReceive('getOrCreateAttendanceRootFolder')->andReturn('attendance-root');
            $drive->shouldReceive('getOrCreateAttendanceYearFolder')
                ->andReturnUsing(function (string $year) use (&$synced) {
                    $synced[] = $year;

                    return 'year-'.$year;
                });
            $drive->shouldReceive('getOrCreateRootFolder')->never();
            $drive->shouldReceive('getOrCreateAcademicYearFolder')->never();
        });

        $academic->createYear('2027–2028', $admin);

        $this->assertContains($current->periodLabel(), $synced);
        $this->assertContains('2027–2028', $synced);
    }

    public function test_connected_drive_page_creates_bssa_attendance_folders(): void
    {
        $admin = User::register([
            'full_name' => 'Drive Page Admin',
            'scholar_id' => 'ADMIN-DRIVE-PAGE-001',
            'email' => 'admin-drive-page@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        app(AcademicSettingsService::class)->current();

        $this->mock(GoogleDriveService::class, function (MockInterface $drive) {
            $drive->shouldReceive('isConnected')->andReturn(true);
            $drive->shouldReceive('getOrCreateAttendanceRootFolder')->once()->andReturn('attendance-root');
            $drive->shouldReceive('getOrCreateAttendanceYearFolder')->atLeast()->once()->andReturn('year-folder');
        });

        $this->actingAs($admin)
            ->get(route('google.drive.test'))
            ->assertOk()
            ->assertSee('BSSA Attendance')
            ->assertSee('BSSA Scholar Documents');
    }

    /**
     * @param  array{scholar_id?: string, full_name?: string, email?: string}  $scholarOverrides
     * @return array{0: User, 1: Attendance}
     */
    private function makePendingAttendance(string $key = 'approve', array $scholarOverrides = []): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City, Surigao del Norte',
            'display_name' => 'Surigao City, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-attendance-drive-'.$key.'-'.uniqid(),
            'is_active' => true,
        ]);

        $club = ScholarshipClub::create([
            'name' => 'Keni Scholars Club',
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);

        $staff = User::register([
            'full_name' => 'Scholar Staff '.$key,
            'email' => 'staff-drive-'.$key.'-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'cellphone_number' => '09170000001',
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $scholar = User::register([
            'full_name' => $scholarOverrides['full_name'] ?? 'Juan Dela Cruz',
            'scholar_id' => $scholarOverrides['scholar_id'] ?? 'BSSA-2026-001',
            'email' => $scholarOverrides['email'] ?? 'juan.dela.cruz.'.$key.'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $event = Event::create([
            'title' => 'Community Clean-Up Drive',
            'description' => 'Barangay clean-up.',
            'location' => 'Surigao City',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHours(3),
            'service_hours' => 8,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $program->id,
        ]);

        $path = 'attendance_photos/'.$scholar->id.'/photo.jpg';
        Storage::disk('public')->put($path, 'verified-photo');

        $attendance = Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'academic_year_start' => 2026,
            'academic_year_end' => 2027,
            'semester' => '2nd Semester',
            'check_in' => now()->subDay()->addMinutes(5),
            'check_out' => now()->subDay()->addHours(2),
            'hours_earned' => 0,
            'status' => Attendance::STATUS_PENDING,
            'photo_path' => $path,
            'photo_original_name' => 'photo.jpg',
            'photo_uploaded_at' => now()->subDay()->addHour(),
        ]);

        return [$staff->fresh(), $attendance->fresh(['user', 'event'])];
    }

    private function mockConnectedDrive(callable $configure): void
    {
        $this->mock(GoogleDriveService::class, function (MockInterface $drive) use ($configure) {
            $drive->shouldReceive('isConnected')->andReturn(true);
            $drive->shouldReceive('sanitizeDriveName')->andReturnUsing(function (string $name): string {
                $clean = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $name) ?? $name;
                $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);

                return mb_substr($clean, 0, 180);
            });
            $configure($drive);
        });
    }

    private function driveFile(string $id): DriveFile
    {
        $file = new DriveFile;
        $file->setId($id);
        $file->setWebViewLink('https://drive.google.com/file/d/'.$id.'/view');

        return $file;
    }
}
