<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Support\PhilippineIslandGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEventsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_page_is_unified_without_city_or_province_scholar_split(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.events'))
            ->assertOk()
            ->assertSee('All Regions')
            ->assertSee('Luzon')
            ->assertSee('Visayas')
            ->assertSee('Mindanao')
            ->assertSee('All Scholarship Clubs')
            ->assertSee('Province')
            ->assertSee('Municipality / City')
            ->assertSee('Search events')
            ->assertDontSee('Scholarship category')
            ->assertDontSee('>City Scholar</option>', false)
            ->assertDontSee('>Province Scholar</option>', false)
            ->assertDontSee('Viewing: City Scholar')
            ->assertDontSee('City Scholar and Province Scholar stay separate')
            ->assertDontSee('Current Admin Scope')
            ->assertDontSee('Showing City Scholar records')
            ->assertDontSee('No registered Scholarship Club found')
            ->assertSee('>Scholarship Club</th>', false)
            ->assertSee('>Participants</th>', false)
            ->assertDontSee('>Scholar Program</th>', false)
            ->assertDontSee('>Program Type</th>', false)
            ->assertSee('No events found');
    }

    public function test_events_list_includes_city_and_province_program_events(): void
    {
        $province = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-evt-dir', null, 'Caraga');
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-evt-dir', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('Dapa Scholars Club', $city->id);

        $cityEvent = $this->makeEvent($city, 'Dapa Coastal Cleanup');
        $provinceEvent = $this->makeEvent($province, 'Province Tree Planting');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.events'))
            ->assertOk()
            ->assertSee($cityEvent->title)
            ->assertSee($provinceEvent->title)
            ->assertSee('Dapa Scholars Club')
            ->assertSee($club->name);
    }

    public function test_region_province_city_and_club_filters_limit_events(): void
    {
        $sdn = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-evt-loc', null, 'Caraga');
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-evt-loc', 'Surigao del Norte', 'Caraga');
        $surigao = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-evt-loc', 'Surigao del Norte', 'Caraga');
        $manila = $this->makeProgram('city_municipality', 'Manila', 'Manila, Metro Manila', 'manila-evt-loc', 'Metro Manila', 'National Capital Region');
        $this->makeProgram('province', 'Metro Manila', 'Metro Manila', 'mm-evt-loc', null, 'National Capital Region');

        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $surigao->id);
        $dapaClub = ScholarshipClub::createForProgram('Dapa Scholars Club', $dapa->id);
        $manilaClub = ScholarshipClub::createForProgram('Manila Scholars Club', $manila->id);

        $keniEvent = $this->makeEvent($surigao, 'KENI Leadership Camp');
        $dapaEvent = $this->makeEvent($dapa, 'Dapa Feeding Program');
        $luzonEvent = $this->makeEvent($manila, 'Manila Orientation');
        $provinceEvent = $this->makeEvent($sdn, 'Province Assembly');

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.events', ['region' => PhilippineIslandGroup::LUZON]))
            ->assertOk()
            ->assertSee($luzonEvent->title)
            ->assertDontSee($keniEvent->title)
            ->assertDontSee($dapaEvent->title);

        $this->actingAs($admin)
            ->get(route('admin.events', ['region' => PhilippineIslandGroup::MINDANAO]))
            ->assertOk()
            ->assertSee($keniEvent->title)
            ->assertSee($dapaEvent->title)
            ->assertSee($provinceEvent->title)
            ->assertDontSee($luzonEvent->title);

        $this->actingAs($admin)
            ->get(route('admin.events', ['location' => $dapa->id]))
            ->assertOk()
            ->assertSee($dapaEvent->title)
            ->assertDontSee($keniEvent->title)
            ->assertDontSee($luzonEvent->title);

        $this->actingAs($admin)
            ->get(route('admin.events', ['club' => $keni->id]))
            ->assertOk()
            ->assertSee($keniEvent->title)
            ->assertDontSee($dapaEvent->title)
            ->assertDontSee($luzonEvent->title)
            ->assertSee('data-province="Surigao del Norte"', false)
            ->assertSee('data-city="Surigao City"', false)
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('Dapa Scholars Club')
            ->assertSee('Manila Scholars Club');

        $this->actingAs($admin)
            ->get(route('admin.events', [
                'location' => $dapa->id,
                'club' => $keni->id,
            ]))
            ->assertOk()
            ->assertDontSee($keniEvent->title)
            ->assertDontSee($dapaEvent->title)
            ->assertSee('No events found');
    }

    public function test_event_search_works_with_filters(): void
    {
        $city = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-evt-search', 'Surigao del Norte', 'Caraga');
        ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);

        $match = $this->makeEvent($city, 'Coastal Cleanup Drive');
        $other = $this->makeEvent($city, 'Leadership Workshop');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.events', ['search' => 'Cleanup']))
            ->assertOk()
            ->assertSee($match->title)
            ->assertDontSee($other->title);
    }

    public function test_participants_column_counts_only_verified_attendance(): void
    {
        $city = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-evt-parts', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);

        $counted = $this->makeEvent($city, 'Verified Participation Drive');
        $empty = $this->makeEvent($city, 'Unverified Workshop');

        $approvedA = $this->makeScholar($city, $club, 'OKA');
        $approvedB = $this->makeScholar($city, $club, 'OKB');
        $pending = $this->makeScholar($city, $club, 'PEND');
        $rejected = $this->makeScholar($city, $club, 'REJ');
        $failed = $this->makeScholar($city, $club, 'FAIL');

        $this->makeAttendance($counted, $approvedA, Attendance::STATUS_APPROVED);
        $this->makeAttendance($counted, $approvedB, Attendance::STATUS_APPROVED);
        $this->makeAttendance($counted, $pending, Attendance::STATUS_PENDING);
        $this->makeAttendance($counted, $rejected, Attendance::STATUS_REJECTED);
        $this->makeAttendance($counted, $failed, Attendance::STATUS_FAILED_CHECK_IN, checkedIn: false);

        $this->makeAttendance($empty, $pending, Attendance::STATUS_PENDING);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.events'))
            ->assertOk()
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('data-label="Participants">2</td>', false)
            ->assertSee('data-label="Participants">0</td>', false)
            ->assertDontSee('>Approved Attendance</th>', false);

        $this->actingAs($admin)
            ->get(route('admin.events.show', $counted))
            ->assertOk()
            ->assertSee('>Scholarship Club</span>', false)
            ->assertSee('>Participants</span>', false)
            ->assertDontSee('>Scholar Program</span>', false)
            ->assertDontSee('>Program Type</span>', false)
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('>2</strong>', false);

        $this->makeAttendance(
            $empty,
            $this->makeScholar($city, $club, 'NEW'),
            Attendance::STATUS_APPROVED
        );

        $this->actingAs($admin)
            ->get(route('admin.events'))
            ->assertOk()
            ->assertSee('data-label="Participants">1</td>', false);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-EVT-DIR-001',
            'email' => 'admin-evt-dir@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    private function makeProgram(
        string $type,
        string $location,
        string $display,
        string $slug,
        ?string $province,
        ?string $region = null,
    ): ScholarshipProgram {
        return ScholarshipProgram::create([
            'location_name' => $location,
            'location_type' => $type,
            'name' => $display,
            'display_name' => $display,
            'province_name' => $province,
            'region_name' => $region,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function makeEvent(ScholarshipProgram $program, string $title): Event
    {
        return Event::create([
            'title' => $title,
            'description' => $title,
            'location' => $program->location_name,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'service_hours' => 4,
            'organizer' => 'BSSA',
            'status' => 'upcoming',
            'scholarship_program_id' => $program->id,
        ]);
    }

    private function makeScholar(ScholarshipProgram $program, ?ScholarshipClub $club, string $suffix): User
    {
        return User::register([
            'full_name' => 'Event Scholar '.$suffix,
            'scholar_id' => 'SCH-EVT-PART-'.$suffix,
            'email' => 'event-scholar-'.strtolower($suffix).'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club?->id,
            'city' => $program->isCityOrMunicipality() ? $program->location_name : null,
            'province' => $program->isProvince() ? $program->location_name : $program->province_name,
        ]);
    }

    private function makeAttendance(
        Event $event,
        User $scholar,
        string $status,
        bool $checkedIn = true,
    ): Attendance {
        return Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'check_in' => $checkedIn ? now()->subHour() : null,
            'check_out' => $checkedIn ? now() : null,
            'hours_earned' => $status === Attendance::STATUS_APPROVED ? 4 : 0,
            'status' => $status,
        ]);
    }
}
