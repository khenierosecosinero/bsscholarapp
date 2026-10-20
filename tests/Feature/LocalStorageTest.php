<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AttendanceArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_scholar_documents_upload_view_and_download_without_google_drive(): void
    {
        Storage::fake('public');
        [$scholar, $type] = $this->makeScholarAndType();

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertDontSee('Google Drive is not connected')
            ->assertDontSee('Connect Google Drive');

        $this->actingAs($scholar)
            ->post(route('user.documents.upload'), [
                'document_type_id' => $type->id,
                'file' => UploadedFile::fake()->create('birth-certificate.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document = Document::query()->where('user_id', $scholar->id)->firstOrFail();
        $this->assertStringContainsString('bssa/documents/', $document->file_path);
        $this->assertStringContainsString('AY ', $document->file_path);
        $this->assertStringContainsString('Surigao del Norte', $document->file_path);
        $this->assertStringContainsString('Surigao City', $document->file_path);
        $this->assertStringContainsString('SNS2023-001', $document->file_path);
        $this->assertNull($document->google_drive_file_id);
        Storage::disk('public')->assertExists($document->file_path);

        $this->actingAs($scholar)->get(route('user.documents.view', $document))->assertOk();
        $this->actingAs($scholar)->get(route('user.documents.download', $document))->assertOk();

        $this->actingAs($scholar)
            ->delete(route('user.documents.destroy', $document))
            ->assertRedirect();

        $document->refresh();
        $this->assertNull($document->file_path);
        $this->assertSame('not_submitted', $document->status);
    }

    public function test_attendance_photos_and_event_images_stay_on_local_storage(): void
    {
        Storage::fake('public');
        [$staff, $attendance, $program] = $this->makePendingAttendance();

        $this->actingAs($staff)
            ->post(route('staff.attendances.approve', $attendance))
            ->assertRedirect();

        $attendance->refresh();
        $this->assertSame(Attendance::STATUS_APPROVED, $attendance->status);
        $this->assertEquals(8, (float) $attendance->hours_earned);
        $this->assertStringStartsWith('bssa/attendance/', (string) $attendance->photo_path);
        $this->assertStringContainsString('Community Clean-Up', (string) $attendance->photo_path);
        Storage::disk('public')->assertExists('attendance_photos/'.$attendance->user_id.'/photo.jpg');
        Storage::disk('public')->assertExists($attendance->photo_path);
        $this->assertNull($attendance->google_drive_file_id);

        app(AttendanceArchiveService::class)->storeApprovedPhoto($attendance->fresh(['user', 'event']));
        $this->assertSame($attendance->photo_path, $attendance->fresh()->photo_path);

        $this->actingAs($staff)
            ->post(route('staff.events.store'), [
                'title' => 'Coastal Planting',
                'description' => 'Mangrove planting.',
                'location' => 'Surigao City',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDay()->addHours(3)->format('Y-m-d H:i:s'),
                'service_hours' => 4,
                'image' => UploadedFile::fake()->image('planting.jpg'),
            ])
            ->assertRedirect();

        $event = Event::query()->where('title', 'Coastal Planting')->firstOrFail();
        $this->assertStringStartsWith('bssa/events/', (string) $event->image_path);
        $this->assertStringContainsString($program->province_name, (string) $event->image_path);
        Storage::disk('public')->assertExists($event->image_path);

        $this->get('/google-drive')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
    }

    /**
     * @return array{0: User, 1: DocumentType}
     */
    private function makeScholarAndType(): array
    {
        $program = $this->makeProgram('local-docs');
        $club = ScholarshipClub::create([
            'name' => 'Local Scholars Club',
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);

        $scholar = User::register([
            'full_name' => 'Khenierose Cosinero',
            'scholar_id' => 'SNS2023-001',
            'email' => 'local-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $type = DocumentType::create([
            'name' => 'Birth Certificate',
            'slug' => 'birth-certificate-local',
            'description' => 'Required',
            'required' => true,
            'scholarship_program_id' => $program->id,
            'submissions_open' => true,
        ]);

        return [$scholar->fresh(), $type];
    }

    /**
     * @return array{0: User, 1: Attendance, 2: ScholarshipProgram}
     */
    private function makePendingAttendance(): array
    {
        $program = $this->makeProgram('local-attendance');
        $club = ScholarshipClub::create([
            'name' => 'Attendance Club',
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);

        $staff = User::register([
            'full_name' => 'Local Staff',
            'email' => 'local-staff@example.com',
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
            'full_name' => 'Juan Dela Cruz',
            'scholar_id' => 'BSSA-2026-001',
            'email' => 'juan.local@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $event = Event::create([
            'title' => 'Community Clean-Up',
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

        return [$staff->fresh(), $attendance->fresh(['user', 'event']), $program];
    }

    private function makeProgram(string $slug): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City, Surigao del Norte',
            'display_name' => 'Surigao City, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => $slug.'-'.uniqid(),
            'is_active' => true,
        ]);
    }
}
