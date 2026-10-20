<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\ScholarSuspension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_block_and_reactivate_a_scholar_in_their_club(): void
    {
        [$staff, $scholar] = $this->makeClubPair();

        $this->actingAs($staff)
            ->get(route('staff.scholars'))
            ->assertOk()
            ->assertSee('Active')
            ->assertSee('Block this account?', false)
            ->assertSee('Suspend this account for 7 days?', false);

        $this->actingAs($staff)
            ->get(route('staff.scholars.show', $scholar))
            ->assertOk()
            ->assertSee('Start Academic Year')
            ->assertDontSee('>Block</button>', false)
            ->assertDontSee('Suspension reason (optional)', false)
            ->assertDontSee('>Suspend</button>', false)
            ->assertDontSee('>Delete Account</button>', false);

        $this->actingAs($staff)
            ->post(route('staff.scholars.block', $scholar))
            ->assertRedirect()
            ->assertSessionHas('success');

        $scholar->refresh();
        $this->assertSame(User::STATUS_BLOCKED, $scholar->status);
        $this->assertSame($scholar->email, $scholar->fresh()->email);

        $this->actingAs($scholar)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('login'));

        $this->post(route('login.post'), [
            'email' => $scholar->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($staff)
            ->get(route('staff.scholars.show', $scholar))
            ->assertOk()
            ->assertSee('Blocked')
            ->assertSee('Reactivate this account?', false);

        $this->actingAs($staff)
            ->post(route('staff.scholars.reactivate', $scholar))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(User::STATUS_APPROVED, $scholar->fresh()->status);

        $this->post(route('login.post'), [
            'email' => $scholar->email,
            'password' => 'password123',
        ])->assertRedirect(route('user.dashboard'));
    }

    public function test_staff_can_suspend_a_scholar_and_cannot_manage_another_club(): void
    {
        [$staff, $scholar] = $this->makeClubPair();
        [, $otherScholar] = $this->makeClubPair('other');

        $this->actingAs($staff)
            ->post(route('staff.scholars.suspend', $scholar))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(User::STATUS_SUSPENDED, $scholar->fresh()->status);

        $this->actingAs($staff)
            ->post(route('staff.scholars.block', $otherScholar))
            ->assertForbidden();

        $this->assertSame(User::STATUS_APPROVED, $otherScholar->fresh()->status);

        $this->actingAs($staff)
            ->post(route('staff.scholars.block', $staff))
            ->assertRedirect(route('staff.scholars'))
            ->assertSessionHas('warning');
    }

    public function test_staff_cannot_block_a_pending_scholar_or_a_staff_account(): void
    {
        [$staff, $scholar] = $this->makeClubPair();
        $scholar->update(['status' => User::STATUS_PENDING]);

        $this->actingAs($staff)
            ->from(route('staff.scholars.show', $scholar))
            ->post(route('staff.scholars.block', $scholar))
            ->assertRedirect(route('staff.scholars.show', $scholar))
            ->assertSessionHas('error');

        $this->assertSame(User::STATUS_PENDING, $scholar->fresh()->status);

        $this->actingAs($staff)
            ->post(route('admin.staff.block', $staff))
            ->assertNotFound();
    }

    public function test_admin_can_suspend_and_reactivate_scholar_staff(): void
    {
        [$staff] = $this->makeClubPair();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.staff'))
            ->assertOk()
            ->assertSee('Active')
            ->assertSee('data-block-url', false)
            ->assertSee('Block this scholar staff account?', false)
            ->assertSee('Suspend this scholar staff account?', false);

        $this->actingAs($admin)
            ->post(route('admin.staff.suspend', $staff))
            ->assertRedirect(route('admin.staff', ['location' => 'all']))
            ->assertSessionHas('success');

        $staff->refresh();
        $this->assertSame(User::STATUS_SUSPENDED, $staff->status);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('login'));

        $this->post(route('login.post'), [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('administrator', session('errors')->get('email')[0]);
        $this->assertSame(0, ScholarSuspension::query()->count());

        $this->actingAs($admin)
            ->get(route('admin.staff.show', $staff))
            ->assertOk()
            ->assertSee('Suspended')
            ->assertSee('Reactivate this account?', false);

        $this->actingAs($admin)
            ->post(route('admin.staff.reactivate', $staff))
            ->assertRedirect();

        $this->assertSame(User::STATUS_APPROVED, $staff->fresh()->status);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_cannot_block_a_scholar_and_a_scholar_cannot_block_anyone(): void
    {
        [$staff, $scholar] = $this->makeClubPair();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.staff.block', $scholar))
            ->assertRedirect(route('admin.staff'))
            ->assertSessionHas('warning');

        $this->actingAs($admin)
            ->post(route('staff.scholars.block', $scholar))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($scholar)
            ->post(route('staff.scholars.suspend', $scholar))
            ->assertForbidden();

        $this->assertSame(User::STATUS_APPROVED, $scholar->fresh()->status);
        $this->assertSame(User::STATUS_APPROVED, $staff->fresh()->status);
    }

    public function test_scholar_suspension_lasts_seven_days_and_then_reactivates_without_staff(): void
    {
        [$staff, $scholar] = $this->makeClubPair('timed');
        $start = Carbon::parse('2026-10-18 21:00:00', config('app.timezone'));
        Carbon::setTestNow($start);

        try {
            $this->actingAs($staff)
                ->post(route('staff.scholars.suspend', $scholar), [
                    'reason' => 'Missed required attendance',
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            $scholar->refresh();
            $suspension = $scholar->openSuspension();
            $this->assertNotNull($suspension);
            $this->assertSame($staff->id, $suspension->issued_by);
            $this->assertSame('Missed required attendance', $suspension->reason);
            $this->assertSame($start->getTimestamp(), $suspension->starts_at->getTimestamp());
            $this->assertSame($start->copy()->addDays(7)->getTimestamp(), $suspension->ends_at->getTimestamp());
            $this->assertSame(User::STATUS_SUSPENDED, $scholar->status);

            auth()->logout();

            $this->actingAs($scholar)
                ->get(route('user.dashboard'))
                ->assertRedirect(route('login'));

            $this->post(route('login.post'), [
                'email' => $scholar->email,
                'password' => 'password123',
            ])->assertSessionHasErrors('email');

            $message = session('errors')->get('email')[0];
            $endsLabel = $suspension->ends_at->timezone(config('app.timezone'))->format('M j, Y g:i A');
            $this->assertStringContainsString('suspended until '.$endsLabel, $message);
            $this->assertStringContainsString('7 days remaining', $message);
            $this->assertGuest();

            $this->actingAs($staff)
                ->get(route('staff.scholars'))
                ->assertOk()
                ->assertSee('Suspended')
                ->assertSee('Started '.$suspension->starts_at->timezone(config('app.timezone'))->format('M j, Y g:i A'), false)
                ->assertSee('Ends '.$endsLabel, false);

            $this->actingAs($staff)
                ->get(route('staff.scholars.show', $scholar))
                ->assertOk()
                ->assertSee('Missed required attendance')
                ->assertSee('Issued by '.$staff->full_name, false);

            Carbon::setTestNow($start->copy()->addDays(7)->addMinute());

            $this->actingAs($staff)
                ->get(route('staff.scholars'))
                ->assertOk()
                ->assertSee('Active');

            $scholar->refresh();
            $this->assertSame(User::STATUS_APPROVED, $scholar->status);
            $this->assertNull($scholar->openSuspension());
            $this->assertNotNull($suspension->fresh()->lifted_at);
            $this->assertSame(ScholarSuspension::LIFT_AUTOMATIC, $suspension->fresh()->lift_type);

            auth()->logout();

            $this->post(route('login.post'), [
                'email' => $scholar->email,
                'password' => 'password123',
            ])->assertRedirect(route('user.dashboard'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_blocking_a_scholar_does_not_expire_after_seven_days(): void
    {
        [$staff, $scholar] = $this->makeClubPair('blocktime');
        $start = Carbon::parse('2026-10-18 21:00:00', config('app.timezone'));
        Carbon::setTestNow($start);

        try {
            $this->actingAs($staff)
                ->post(route('staff.scholars.suspend', $scholar))
                ->assertRedirect();

            $this->actingAs($staff)
                ->post(route('staff.scholars.block', $scholar))
                ->assertRedirect();

            $this->assertSame(User::STATUS_BLOCKED, $scholar->fresh()->status);
            $this->assertNull($scholar->fresh()->openSuspension());

            Carbon::setTestNow($start->copy()->addDays(8));

            $this->actingAs($staff)->get(route('staff.scholars'))->assertOk();

            $this->assertSame(User::STATUS_BLOCKED, $scholar->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function makeClubPair(string $suffix = 'main'): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Dapa '.$suffix,
            'location_type' => 'city_municipality',
            'name' => 'Dapa '.$suffix,
            'display_name' => 'Dapa '.$suffix,
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-access-'.$suffix,
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Club '.$suffix, $program->id);

        $staff = User::register([
            'full_name' => 'Staff '.$suffix,
            'scholar_id' => 'STAFF-ACCESS-'.strtoupper($suffix),
            'email' => 'staff-access-'.$suffix.'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $scholar = User::register([
            'full_name' => 'Scholar '.$suffix,
            'scholar_id' => 'SCH-ACCESS-'.strtoupper($suffix),
            'email' => 'scholar-access-'.$suffix.'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        return [$staff, $scholar];
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'Access Admin',
            'scholar_id' => 'ADMIN-ACCESS-001',
            'email' => 'admin-access@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }
}
