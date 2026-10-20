<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffAttendanceSchoolFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_page_filters_both_lists_and_counts_by_school(): void
    {
        [$staff, $sti, $snsu, $stiScholar, $snsuScholar, $unassigned, $event, $otherEvent] = $this->makeEventAttendance();

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('School/University')
            ->assertSee('All Schools/Universities')
            ->assertSee('No School Specified')
            ->assertSee($sti->name)
            ->assertSee($snsu->name)
            ->assertSee($stiScholar->full_name)
            ->assertSee($snsuScholar->full_name)
            ->assertSee($unassigned->full_name)
            ->assertSee('2 approved')
            ->assertSee('0 pending')
            ->assertSee('1 failed to check in')
            ->assertSee('4.00 hrs')
            ->assertSee('Approved')
            ->assertDontSee('>Reset</a>', false);

        $this->actingAs($staff)
            ->get(route('staff.attendance', [
                'event' => $event->id,
                'school' => 'id:'.$sti->id,
            ]))
            ->assertOk()
            ->assertSee($stiScholar->full_name)
            ->assertSee('4.00 hrs')
            ->assertSee('Approved')
            ->assertDontSee($snsuScholar->full_name)
            ->assertDontSee($unassigned->full_name)
            ->assertSee('1 approved')
            ->assertSee('0 pending')
            ->assertSee('0 failed to check in')
            ->assertSee('Reset');

        $this->actingAs($staff)
            ->get(route('staff.attendance', [
                'event' => $event->id,
                'school' => 'id:'.$snsu->id,
            ]))
            ->assertOk()
            ->assertSee($snsuScholar->full_name)
            ->assertSee('Failed to Check In')
            ->assertDontSee($stiScholar->full_name)
            ->assertSee('0 approved')
            ->assertSee('0 pending')
            ->assertSee('1 failed to check in');

        $this->actingAs($staff)
            ->get(route('staff.attendance', [
                'event' => $event->id,
                'school' => 'unassigned',
            ]))
            ->assertOk()
            ->assertSee($unassigned->full_name)
            ->assertDontSee($stiScholar->full_name)
            ->assertDontSee($snsuScholar->full_name)
            ->assertSee('1 approved')
            ->assertSee('0 pending')
            ->assertSee('0 failed to check in');

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $otherEvent->id]))
            ->assertOk()
            ->assertSee($snsuScholar->full_name)
            ->assertSee('1 approved')
            ->assertSee('0 pending')
            ->assertSee('0 failed to check in')
            ->assertDontSee($stiScholar->full_name);

        $this->assertSame(4.0, (float) Attendance::query()->where('user_id', $stiScholar->id)->where('event_id', $event->id)->value('hours_earned'));
        $this->assertSame(Attendance::STATUS_APPROVED, Attendance::query()->where('user_id', $stiScholar->id)->where('event_id', $event->id)->value('status'));
    }

    public function test_attendance_sections_follow_approval_status_for_the_selected_event_and_school(): void
    {
        Storage::fake('public');

        [$staff, $sti, $snsu, $approvedScholar, $failedScholar, , $event] = $this->makeEventAttendance();
        $program = ScholarshipProgram::query()->where('slug', 'attendance-school-filter')->firstOrFail();
        $club = ScholarshipClub::query()->where('name', 'Attendance Club')->firstOrFail();
        $pendingScholar = $this->makeScholar($program, $club, $sti, 'Pending Scholar', 'pending');
        $rejectedScholar = $this->makeScholar($program, $club, $snsu, 'Rejected Scholar', 'rejected');

        $pending = $this->checkedInAttendance($event, $pendingScholar, Attendance::STATUS_PENDING);
        $rejected = $this->checkedInAttendance($event, $rejectedScholar, Attendance::STATUS_REJECTED);
        $rejected->update(['hours_earned' => 0]);

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id]))
            ->assertOk()
            ->assertSeeInOrder([
                'Scholars Who Checked In (Approved)',
                $approvedScholar->full_name,
                'Pending Scholars (For Verification)',
                $pendingScholar->full_name,
                'Pending',
                'View',
                'Approve',
                'Reject',
                'Scholars Who Failed to Check In',
                $failedScholar->full_name,
                '0.00 hrs',
                'Failed to Check In',
            ])
            ->assertDontSee($rejectedScholar->full_name)
            ->assertSee('2 approved')
            ->assertSee('1 pending')
            ->assertSee('1 failed to check in');

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id, 'school' => 'id:'.$sti->id]))
            ->assertOk()
            ->assertSee($pendingScholar->full_name)
            ->assertSee($approvedScholar->full_name)
            ->assertDontSee($failedScholar->full_name)
            ->assertSee('1 approved')
            ->assertSee('1 pending')
            ->assertSee('0 failed to check in');

        $this->actingAs($staff)
            ->from(route('staff.attendance', ['event' => $event->id, 'school' => 'id:'.$sti->id]))
            ->post(route('staff.attendances.approve', $pending))
            ->assertRedirect(route('staff.attendance', ['event' => $event->id, 'school' => 'id:'.$sti->id]));

        $pending->refresh();
        $this->assertSame(Attendance::STATUS_APPROVED, $pending->status);
        $this->assertEquals(4, (float) $pending->hours_earned);

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id, 'school' => 'id:'.$sti->id]))
            ->assertOk()
            ->assertSeeInOrder([
                'Scholars Who Checked In (Approved)',
                $pendingScholar->full_name,
                'Approved',
                'Pending Scholars (For Verification)',
            ])
            ->assertSee('2 approved')
            ->assertSee('0 pending')
            ->assertDontSee('>Approve</button>', false);

        $another = $this->makeScholar($program, $club, $sti, 'Second Pending Scholar', 'pending-two');
        $second = $this->checkedInAttendance($event, $another, Attendance::STATUS_PENDING);

        $this->actingAs($staff)
            ->from(route('staff.attendance', ['event' => $event->id]))
            ->post(route('staff.attendances.reject', $second), ['remarks' => 'Photo is unclear'])
            ->assertRedirect(route('staff.attendance', ['event' => $event->id]))
            ->assertSessionHas('success');

        $second->refresh();
        $this->assertSame(Attendance::STATUS_REJECTED, $second->status);
        $this->assertEquals(0, (float) $second->hours_earned);

        $this->actingAs($staff)->get(route('staff.attendance', ['event' => $event->id]))->assertOk();

        $this->actingAs($staff)
            ->get(route('staff.attendance', ['event' => $event->id]))
            ->assertOk()
            ->assertDontSee($another->full_name)
            ->assertDontSee($rejectedScholar->full_name)
            ->assertSee('3 approved')
            ->assertSee('0 pending');
    }

    public function test_attendance_event_cards_show_schedule_status_and_counts(): void
    {
        [$staff, , , , , , $event, $otherEvent] = $this->makeEventAttendance();

        $this->actingAs($staff)
            ->get(route('staff.attendance'))
            ->assertOk()
            ->assertSee('staff-attendance-event-grid', false)
            ->assertSee('staff-attendance-event-card', false)
            ->assertSee($event->title)
            ->assertSee($otherEvent->title)
            ->assertSee('Surigao City Gymnasium')
            ->assertSee('City Hall')
            ->assertSee('Service Hours: 4.00')
            ->assertSee('2 approved')
            ->assertSee('0 pending')
            ->assertSee('1 failed to check in')
            ->assertSee('CLOSED')
            ->assertSee(route('staff.attendance', ['event' => $event->id]), false);
    }

    /**
     * @return array{0: User, 1: ScholarshipClubSchool, 2: ScholarshipClubSchool, 3: User, 4: User, 5: User, 6: Event, 7: Event}
     */
    private function makeEventAttendance(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => 'attendance-school-filter',
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Attendance Club', $program->id);
        $sti = ScholarshipClubSchool::createForClub('STI College Surigao', $club->id);
        $snsu = ScholarshipClubSchool::createForClub('Surigao del Norte State University', $club->id);

        $staff = User::register([
            'full_name' => 'Attendance Staff',
            'scholar_id' => 'STAFF-ATT-SCHOOL',
            'email' => 'attendance-school-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $stiScholar = $this->makeScholar($program, $club, $sti, 'STI Scholar', 'sti');
        $snsuScholar = $this->makeScholar($program, $club, $snsu, 'SNSU Scholar', 'snsu');
        $unassigned = User::register([
            'full_name' => 'No School Scholar',
            'scholar_id' => 'SCH-ATT-NONE',
            'email' => 'attendance-no-school@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'school_university' => null,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $event = Event::create([
            'title' => 'Leadership Training',
            'location' => 'Surigao City Gymnasium',
            'starts_at' => now()->subDay()->setTime(8, 0),
            'ends_at' => now()->subDay()->setTime(17, 0),
            'service_hours' => 4,
            'status' => Event::STATUS_COMPLETED,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);

        $this->register($event, $stiScholar, checkedIn: true);
        $this->register($event, $snsuScholar, checkedIn: false);
        $this->register($event, $unassigned, checkedIn: true);

        $otherEvent = Event::create([
            'title' => 'Campus Cleanup',
            'location' => 'City Hall',
            'starts_at' => now()->subDays(2)->setTime(8, 0),
            'ends_at' => now()->subDays(2)->setTime(12, 0),
            'service_hours' => 3,
            'status' => Event::STATUS_COMPLETED,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);
        $this->register($otherEvent, $snsuScholar, checkedIn: true);

        return [$staff, $sti, $snsu, $stiScholar, $snsuScholar, $unassigned, $event, $otherEvent];
    }

    private function makeScholar(ScholarshipProgram $program, ScholarshipClub $club, ScholarshipClubSchool $school, string $name, string $suffix): User
    {
        return User::register([
            'full_name' => $name,
            'scholar_id' => 'SCH-ATT-'.strtoupper($suffix),
            'email' => 'attendance-'.$suffix.'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'school_university' => $school->name,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
    }

    private function checkedInAttendance(Event $event, User $scholar, string $status): Attendance
    {
        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);

        $photo = 'attendance_photos/'.$scholar->id.'/photo.jpg';
        Storage::disk('public')->put($photo, 'attendance-photo');

        return Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'academic_year_start' => 2026,
            'academic_year_end' => 2027,
            'semester' => '1st Semester',
            'check_in' => $event->starts_at->copy()->addHour(),
            'check_out' => $event->ends_at,
            'hours_earned' => $event->service_hours,
            'status' => $status,
            'photo_path' => $photo,
            'photo_original_name' => 'photo.jpg',
            'photo_uploaded_at' => $event->starts_at,
        ]);
    }

    private function register(Event $event, User $scholar, bool $checkedIn): void
    {
        EventRegistration::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
        ]);

        if (! $checkedIn) {
            return;
        }

        Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'academic_year_start' => 2026,
            'academic_year_end' => 2027,
            'semester' => '1st Semester',
            'check_in' => $event->starts_at->copy()->addHour(),
            'check_out' => $event->ends_at,
            'hours_earned' => $event->service_hours,
            'status' => Attendance::STATUS_APPROVED,
        ]);
    }
}
