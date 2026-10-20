<?php

namespace Tests\Feature;

use App\Mail\WorkflowNotificationMail;
use App\Models\DocumentType;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\ScholarNotification;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ScholarService;
use App\Services\WorkflowMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class WorkflowMailTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        config(['mail.force_after_commit' => false]);
        parent::tearDown();
    }

    public function test_account_document_and_event_workflows_email_the_right_people_once(): void
    {
        Mail::fake();
        [$program, $club, $staff, $admin, $scholar] = $this->makePeople();

        app(AccountService::class)->provisionNewAccount($scholar->fresh());

        Mail::assertSent(WorkflowNotificationMail::class, function (WorkflowNotificationMail $mail) use ($scholar) {
            return $mail->hasTo($scholar->email)
                && $mail->title === 'Account Pending Approval'
                && str_contains($mail->actionUrl, '/login') === false
                && $mail->actionUrl !== '';
        });
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($staff->email) && $mail->title === 'New scholar registration');
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($admin->email) && $mail->title === 'New scholar registration');

        $this->assertSame(1, ScholarNotification::query()->where('user_id', $scholar->id)->where('title', 'Account Pending Approval')->count());

        Mail::fake();
        $scholar->update(['status' => User::STATUS_APPROVED]);
        app(ScholarService::class)->notify(
            $scholar->fresh(),
            'Account Approved',
            'Your scholar account has been approved. You now have full access to all system features.',
            'system'
        );
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($scholar->email) && str_contains($mail->actionUrl, '/user/notifications'));

        Mail::fake();
        $scholar->update(['notification_preferences' => ['event_reminders' => false]]);
        app(ScholarService::class)->notify($scholar->fresh(), 'Event Confirmed', 'You are confirmed for Assembly.', 'event_reminder');
        Mail::assertNothingSent();
        $this->assertSame(1, ScholarNotification::query()->where('user_id', $scholar->id)->where('title', 'Event Confirmed')->count());

        Mail::fake();
        $scholar->update(['notification_preferences' => ['document_updates' => true]]);
        $type = DocumentType::create([
            'name' => 'Birth Certificate',
            'slug' => 'birth-mail',
            'description' => 'Copy',
            'required' => true,
            'scholarship_program_id' => $program->id,
            'submission_deadline' => Carbon::parse('2026-10-21 18:00:00', config('app.timezone')),
        ]);
        Carbon::setTestNow(Carbon::parse('2026-10-21 06:00:00', config('app.timezone')));
        app(ScholarService::class)->notifyDocumentWindow($type, 'approaching');
        app(ScholarService::class)->notifyDocumentWindow($type, 'approaching');
        Mail::assertSent(WorkflowNotificationMail::class, 1);
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($scholar->email) && str_contains($mail->title, 'Deadline approaching'));

        Mail::fake();
        app(WorkflowMailService::class)->sendToAddress('not-an-email', 'Scholar', 'Account Rejected', 'Not approved.', 'http://127.0.0.1:8000/login', 'Go to sign in', 'bad-email');
        Mail::assertNothingSent();
    }

    public function test_a_rolled_back_transaction_does_not_send_mail(): void
    {
        Mail::fake();
        config(['mail.force_after_commit' => true]);
        $sent = false;

        try {
            DB::transaction(function () use (&$sent) {
                app(WorkflowMailService::class)->deliverAfterCommit(function () use (&$sent) {
                    $sent = true;
                });

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        $this->assertFalse($sent);

        $sent = false;
        DB::transaction(function () use (&$sent) {
            app(WorkflowMailService::class)->deliverAfterCommit(function () use (&$sent) {
                $sent = true;
            });
        });
        $this->assertTrue($sent);
    }

    /**
     * @return array{0: ScholarshipProgram, 1: ScholarshipClub, 2: User, 3: User, 4: User}
     */
    private function makePeople(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => 'mail-program',
            'is_active' => true,
        ]);
        $club = ScholarshipClub::createForProgram('Mail Club', $program->id);

        $staff = User::register([
            'full_name' => 'Mail Staff',
            'scholar_id' => 'STAFF-MAIL',
            'email' => 'mail-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
        ]);
        $admin = User::register([
            'full_name' => 'Mail Admin',
            'scholar_id' => 'ADMIN-MAIL',
            'email' => 'mail-admin@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_admin' => true,
        ]);
        $scholar = User::register([
            'full_name' => 'Mail Scholar',
            'scholar_id' => 'SCH-MAIL',
            'email' => 'mail-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_PENDING,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
        ]);

        return [$program, $club, $staff, $admin, $scholar];
    }
}
