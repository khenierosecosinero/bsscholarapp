<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminEventAttendancePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-EVT-PHOTO-001',
            'email' => 'admin-event-photo@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    private function makeCityProgram(): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City, Surigao del Norte',
            'display_name' => 'Surigao City, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-event-photo',
            'is_active' => true,
        ]);
    }

    private function makeScholar(ScholarshipProgram $program, string $suffix): User
    {
        return User::register([
            'full_name' => 'Scholar '.$suffix,
            'scholar_id' => 'SCH-EVT-'.$suffix,
            'email' => 'scholar-evt-'.$suffix.'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
    }

    private function makeEvent(ScholarshipProgram $program): Event
    {
        return Event::create([
            'title' => 'Tree Planting',
            'description' => 'Community planting activity.',
            'location' => 'Surigao City',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHours(3),
            'service_hours' => 20,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $program->id,
        ]);
    }

    private function storePhoto(User $scholar, string $name = 'photo.jpg'): string
    {
        $path = 'attendance_photos/'.$scholar->id.'/'.$name;
        Storage::disk('public')->put($path, 'fake-photo');

        return $path;
    }

    private function makeAttendance(
        Event $event,
        User $scholar,
        string $status,
        ?string $photoPath = null,
    ): Attendance {
        return Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'check_in' => now()->subDay()->addMinutes(5),
            'check_out' => now()->subDay()->addHours(2),
            'hours_earned' => $status === Attendance::STATUS_APPROVED ? 20 : 0,
            'status' => $status,
            'photo_path' => $photoPath,
            'photo_original_name' => $photoPath ? 'photo.jpg' : null,
            'photo_uploaded_at' => $photoPath ? now()->subDay()->addHour() : null,
        ]);
    }

    public function test_admin_event_details_show_only_approved_attendance_photos(): void
    {
        Storage::fake('public');

        $program = $this->makeCityProgram();
        $event = $this->makeEvent($program);
        $approved = $this->makeScholar($program, 'APPROVED');
        $pending = $this->makeScholar($program, 'PENDING');
        $rejected = $this->makeScholar($program, 'REJECTED');
        $noPhoto = $this->makeScholar($program, 'NOPHOTO');

        EventRegistration::create(['user_id' => $approved->id, 'event_id' => $event->id, 'status' => 'confirmed']);
        EventRegistration::create(['user_id' => $pending->id, 'event_id' => $event->id, 'status' => 'confirmed']);
        EventRegistration::create(['user_id' => $rejected->id, 'event_id' => $event->id, 'status' => 'confirmed']);
        EventRegistration::create(['user_id' => $noPhoto->id, 'event_id' => $event->id, 'status' => 'confirmed']);

        $approvedAttendance = $this->makeAttendance($event, $approved, Attendance::STATUS_APPROVED, $this->storePhoto($approved, 'approved.jpg'));
        $pendingAttendance = $this->makeAttendance($event, $pending, Attendance::STATUS_PENDING, $this->storePhoto($pending, 'pending.jpg'));
        $rejectedAttendance = $this->makeAttendance($event, $rejected, Attendance::STATUS_REJECTED, $this->storePhoto($rejected, 'rejected.jpg'));
        $this->makeAttendance($event, $noPhoto, Attendance::STATUS_APPROVED);

        $approvedUrl = route('admin.events.attendances.photo', [$event, $approvedAttendance]);
        $pendingUrl = route('admin.events.attendances.photo', [$event, $pendingAttendance]);
        $rejectedUrl = route('admin.events.attendances.photo', [$event, $rejectedAttendance]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.events.show', $event))
            ->assertOk()
            ->assertSee('Attendance Photo')
            ->assertSee('Check-In')
            ->assertSee('Service Hours Earned')
            ->assertSee($approved->full_name)
            ->assertSee($approvedUrl)
            ->assertSee('data-avatar-preview="'.$approvedUrl.'"', false)
            ->assertDontSee($pendingUrl)
            ->assertDontSee($rejectedUrl)
            ->assertSee('No approved photo');
    }

    public function test_admin_can_view_approved_attendance_photo_for_the_matching_event(): void
    {
        Storage::fake('public');

        $program = $this->makeCityProgram();
        $event = $this->makeEvent($program);
        $scholar = $this->makeScholar($program, 'VIEW');
        $attendance = $this->makeAttendance($event, $scholar, Attendance::STATUS_APPROVED, $this->storePhoto($scholar));

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.events.attendances.photo', [$event, $attendance]))
            ->assertOk();
    }

    public function test_admin_cannot_view_pending_or_rejected_attendance_photos(): void
    {
        Storage::fake('public');

        $program = $this->makeCityProgram();
        $event = $this->makeEvent($program);
        $pendingScholar = $this->makeScholar($program, 'PEND');
        $rejectedScholar = $this->makeScholar($program, 'REJ');

        $pending = $this->makeAttendance($event, $pendingScholar, Attendance::STATUS_PENDING, $this->storePhoto($pendingScholar, 'pending.jpg'));
        $rejected = $this->makeAttendance($event, $rejectedScholar, Attendance::STATUS_REJECTED, $this->storePhoto($rejectedScholar, 'rejected.jpg'));

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.events.attendances.photo', [$event, $pending]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.events.attendances.photo', [$event, $rejected]))
            ->assertNotFound();
    }

    public function test_admin_cannot_view_an_attendance_photo_from_a_different_event(): void
    {
        Storage::fake('public');

        $program = $this->makeCityProgram();
        $event = $this->makeEvent($program);
        $otherEvent = Event::create([
            'title' => 'Coastal Cleanup',
            'description' => 'Cleanup activity.',
            'location' => 'Surigao City',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(3),
            'service_hours' => 8,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $program->id,
        ]);
        $scholar = $this->makeScholar($program, 'OTHER');
        $attendance = $this->makeAttendance($event, $scholar, Attendance::STATUS_APPROVED, $this->storePhoto($scholar));

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.events.attendances.photo', [$otherEvent, $attendance]))
            ->assertNotFound();
    }
}
