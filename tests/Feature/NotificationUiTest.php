<?php

namespace Tests\Feature;

use App\Models\AdminBackupEmail;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_renders_shared_flash_alerts_for_success_error_and_warning(): void
    {
        $this->withSession([
            'success' => 'Welcome back.',
            'error' => 'Something went wrong.',
            'warning' => 'Please review your details.',
        ])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('bssa-alert--success', false)
            ->assertSee('bssa-alert--error', false)
            ->assertSee('bssa-alert--warning', false)
            ->assertSee('Success!')
            ->assertSee('bssa-alert__icon-svg', false)
            ->assertSee('data-bssa-auto-dismiss="5000"', false)
            ->assertSee('data-bssa-alert-close', false)
            ->assertSee('Welcome back.')
            ->assertSee('Something went wrong.')
            ->assertSee('Please review your details.')
            ->assertSee('id="bssa-toast-host"', false);

        $this->flushSession();

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('bssa-alert--success', false)
            ->assertDontSee('Scholar staff account created successfully');
    }

    public function test_login_validation_errors_use_shared_alert_component(): void
    {
        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.post'), [
                'email' => '',
                'password' => '',
            ])
            ->assertOk()
            ->assertSee('bssa-alert--error', false)
            ->assertSee('Please fix');
    }

    public function test_admin_settings_uses_branded_confirm_for_backup_email_removal(): void
    {
        $admin = $this->makeAdmin();
        AdminBackupEmail::query()->create([
            'user_id' => $admin->id,
            'email' => 'backup@example.com',
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('id="staff-confirm-modal"', false)
            ->assertSee('data-confirm-title="Remove this backup recovery email?"', false)
            ->assertSee('data-confirm-name="backup@example.com"', false)
            ->assertDontSee("onclick=\"return confirm('Remove this backup recovery email?');\"", false);
    }

    public function test_admin_flash_success_uses_shared_alert_component(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['success' => 'Password updated successfully.', 'info' => 'Tip saved.'])
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('bssa-alert--success', false)
            ->assertSee('bssa-alert--info', false)
            ->assertSee('Password updated successfully.')
            ->assertSee('data-bssa-auto-dismiss="5000"', false)
            ->assertSee('Success!');
    }

    public function test_scholar_documents_page_uses_shared_alerts_and_upload_confirm(): void
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-notify-docs',
            'is_active' => true,
        ]);
        $club = ScholarshipClub::createForProgram('Docs Club', $program->id);
        $scholar = User::register([
            'full_name' => 'Docs Scholar',
            'scholar_id' => 'SCH-NOTIFY-DOCS',
            'email' => 'docs-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        DocumentType::create([
            'name' => 'ID Card',
            'slug' => 'id-card-notify',
            'description' => 'Valid ID',
            'scholarship_program_id' => $program->id,
            'required' => true,
        ]);

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertSee('id="staff-confirm-modal"', false)
            ->assertSee('id="upload-form"', false)
            ->assertSee('id="file-input"', false)
            ->assertDontSee('UPLOAD NEW DOCUMENT')
            ->assertSee('Why do we need these documents?')
            ->assertSee('documents-info-card', false)
            ->assertSee('bssa-alert--info', false);
    }

    public function test_staff_and_scholar_layouts_share_confirm_modal_without_native_confirm(): void
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-notify-ui',
            'is_active' => true,
        ]);
        $club = ScholarshipClub::createForProgram('Notify Club', $program->id);

        $staff = User::register([
            'full_name' => 'Notify Staff',
            'scholar_id' => 'STAFF-NOTIFY-001',
            'email' => 'notify-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $scholar = User::register([
            'full_name' => 'Notify Scholar',
            'scholar_id' => 'SCH-NOTIFY-001',
            'email' => 'notify-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $type = DocumentType::create([
            'name' => 'Transcript',
            'slug' => 'transcript-notify-ui',
            'description' => 'Official transcript',
            'scholarship_program_id' => $program->id,
            'required' => true,
        ]);

        Document::create([
            'user_id' => $scholar->id,
            'document_type_id' => $type->id,
            'status' => 'pending',
            'file_path' => 'documents/'.$scholar->id.'/transcript.pdf',
            'original_name' => 'transcript.pdf',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.documents'))
            ->assertOk()
            ->assertSee('id="staff-confirm-modal"', false)
            ->assertSee('data-confirm-title="Remove Transcript?"', false)
            ->assertDontSee("onsubmit=\"return confirm(", false);

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertSee('id="staff-confirm-modal"', false)
            ->assertSee('data-confirm-title="Delete this Transcript?"', false)
            ->assertSee('id="bssa-toast-host"', false)
            ->assertDontSee("onsubmit=\"return confirm(", false);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'Notify Admin',
            'scholar_id' => 'ADMIN-NOTIFY-001',
            'email' => 'notify-admin@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }
}
