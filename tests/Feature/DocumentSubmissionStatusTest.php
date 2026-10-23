<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentSubmissionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_open_and_close_each_document_type_without_changing_records(): void
    {
        Storage::fake('public');
        [$staff, $otherStaff, $scholar, $birth, $studentId, $document] = $this->makeDocuments();

        $this->actingAs($staff)
            ->get(route('staff.documents.show', $birth))
            ->assertOk()
            ->assertSee('Open Submissions')
            ->assertSee('Close Submissions')
            ->assertSee('Back to Documents')
            ->assertSee('All Schools/Universities')
            ->assertSee('No School Specified')
            ->assertSee('No deadline set')
            ->assertSee('Search by scholar name or ID')
            ->assertSee('Approve')
            ->assertSee($scholar->full_name)
            ->assertSee('1 submitted')
            ->assertSee('1 pending');

        $openPage = $this->actingAs($staff)
            ->get(route('staff.documents.show', $birth))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('disabled>Open Submissions', $openPage);
        $this->assertStringNotContainsString('disabled>Close Submissions', $openPage);
        $this->assertStringContainsString('staff-submission-status-badge">OPEN', $openPage);

        $this->actingAs($scholar)
            ->post(route('staff.documents.submissions.close', $birth))
            ->assertForbidden();

        $this->actingAs($otherStaff)
            ->post(route('staff.documents.submissions.close', $birth))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('staff.documents.submissions.close', $birth))
            ->assertRedirect(route('staff.documents.show', $birth));

        $birth->refresh();
        $document->refresh();
        $this->assertFalse($birth->submissionsAreOpen());
        $this->assertNotNull($birth->submissions_closed_at);
        $this->assertSame('pending', $document->status);
        $this->assertSame('birth.pdf', $document->original_name);
        $this->assertSame('documents/birth.pdf', $document->file_path);

        $studentId->refresh();
        $this->assertTrue($studentId->submissionsAreOpen());

        $closedPage = $this->actingAs($staff)
            ->get(route('staff.documents.show', $birth))
            ->assertOk()
            ->assertSee('Existing files and review statuses stay unchanged.')
            ->getContent();

        $this->assertStringContainsString('disabled>Close Submissions', $closedPage);
        $this->assertStringNotContainsString('disabled>Open Submissions', $closedPage);
        $this->assertStringContainsString('staff-submission-status-badge">CLOSED', $closedPage);

        $this->actingAs($staff)
            ->get(route('staff.documents'))
            ->assertOk()
            ->assertSee('CLOSED')
            ->assertSee('OPEN');

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertDontSee('Submissions are currently closed')
            ->assertDontSee('UPLOAD NEW DOCUMENT')
            ->assertSee('DOCUMENT GUIDELINES')
            ->assertSee('No deadline set')
            ->assertDontSee("value='".$birth->id."'", false)
            ->assertSee("value='".$studentId->id."'", false);

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $birth->id,
                'file' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('user.documents'))
            ->assertSessionHas('error');

        $document->refresh();
        $this->assertSame('pending', $document->status);
        $this->assertSame('birth.pdf', $document->original_name);

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->delete(route('user.documents.destroy', $document))
            ->assertRedirect(route('user.documents'))
            ->assertSessionHas('error');

        $document->refresh();
        $this->assertSame('documents/birth.pdf', $document->file_path);
        $this->assertSame('pending', $document->status);

        $this->actingAs($staff)
            ->post(route('staff.documents.submissions.open', $birth))
            ->assertRedirect();

        $birth->refresh();
        $this->assertTrue($birth->submissionsAreOpen());
        $document->refresh();
        $this->assertSame('pending', $document->status);
        $this->assertSame('birth.pdf', $document->original_name);

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertDontSee('Submissions are currently closed')
            ->assertDontSee('UPLOAD NEW DOCUMENT')
            ->assertSee("value='".$birth->id."'", false);

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $birth->id,
                'file' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('user.documents'))
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame('pending', $document->status);
        $this->assertSame('replacement.pdf', $document->original_name);
        $this->assertStringStartsWith('bssa/documents/', (string) $document->file_path);
    }

    public function test_staff_document_actions_stay_grouped_and_update_review_status(): void
    {
        Storage::fake('public');
        [$staff, $otherStaff, $scholar, $birth, , $document] = $this->makeDocuments();

        $page = $this->actingAs($staff)
            ->get(route('staff.documents.show', $birth))
            ->assertOk()
            ->assertSee('Review')
            ->assertSee('Download')
            ->assertSee('Reject')
            ->assertSee('Approve')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/staff-doc-review-actions[\s\S]*?>\s*Review<\/a>\s*<a[^>]*>Download<\/a>[\s\S]*staff-btn-danger"[^>]*>Reject<\/button>[\s\S]*staff-btn-success"[^>]*>Approve<\/button>/',
            $page
        );
        $this->assertStringContainsString(route('staff.documents.view', $document), $page);
        $this->assertStringContainsString(route('staff.documents.download', $document), $page);
        $this->assertStringContainsString(route('staff.documents.status', $document), $page);
        $this->assertStringNotContainsString('Set pending', $page);
        $this->assertStringNotContainsString('Reason (optional)', $page);
        $this->assertStringNotContainsString('data-label="File"', $page);

        $this->actingAs($scholar)
            ->patch(route('staff.documents.status', $document), ['status' => 'approved'])
            ->assertForbidden();
        $this->actingAs($otherStaff)
            ->patch(route('staff.documents.status', $document), ['status' => 'rejected', 'review_notes' => 'No'])
            ->assertForbidden();

        $document->refresh();
        $this->assertSame('pending', $document->status);
        $this->assertSame('documents/birth.pdf', $document->file_path);

        $this->actingAs($staff)
            ->patch(route('staff.documents.status', $document), ['status' => 'approved'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document->refresh();
        $reviewedAt = $document->reviewed_at;
        $this->assertSame('approved', $document->status);
        $this->assertSame($staff->id, $document->reviewed_by);
        $this->assertSame('documents/birth.pdf', $document->file_path);

        $approvedPage = $this->actingAs($staff)
            ->get(route('staff.documents.show', $birth))
            ->assertOk()
            ->assertSee('0 pending')
            ->assertSee('1 approved')
            ->assertSee('0 rejected')
            ->getContent();
        $this->assertMatchesRegularExpression('/staff-btn-sm staff-btn-success" disabled/', $approvedPage);
        $this->assertStringNotContainsString('Set pending', $approvedPage);
        $this->assertStringNotContainsString('Reason (optional)', $approvedPage);

        $this->actingAs($staff)
            ->patch(route('staff.documents.status', $document), ['status' => 'approved'])
            ->assertRedirect()
            ->assertSessionHas('success', "Birth Certificate for {$scholar->full_name} is already Approved.");

        $document->refresh();
        $this->assertSame('approved', $document->status);
        $this->assertTrue($document->reviewed_at->eq($reviewedAt));

        $this->actingAs($staff)
            ->patch(route('staff.documents.status', $document), [
                'status' => 'rejected',
                'review_notes' => 'Blurry scan',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame('rejected', $document->status);
        $this->assertSame('Blurry scan', $document->review_notes);
        $this->assertSame('documents/birth.pdf', $document->file_path);

        $rejectedPage = $this->actingAs($staff)
            ->get(route('staff.documents.show', $birth))
            ->assertOk()
            ->assertSee('0 pending')
            ->assertSee('0 approved')
            ->assertSee('1 rejected')
            ->assertSee('Blurry scan')
            ->assertDontSee('Set pending')
            ->assertDontSee('Reason (optional)')
            ->getContent();
        $this->assertMatchesRegularExpression('/staff-btn-sm staff-btn-danger" disabled/', $rejectedPage);
        $this->assertDoesNotMatchRegularExpression('/staff-btn-sm staff-btn-success" disabled/', $rejectedPage);
    }

    public function test_scholar_can_replace_a_rejected_document_without_losing_its_history(): void
    {
        Storage::fake('public');
        [$staff, $otherStaff, $scholar, $birth, , $document] = $this->makeDocuments();
        Storage::disk('public')->put($document->file_path, 'original-scan');

        $this->actingAs($staff)
            ->patch(route('staff.documents.status', $document), [
                'status' => 'rejected',
                'review_notes' => 'Blurry scan',
            ])
            ->assertRedirect();

        $birth->update([
            'submission_deadline' => now()->subDay(),
            'submissions_open' => false,
            'submissions_closed_at' => now(),
        ]);

        $rejectedPage = $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertSee('Upload Replacement')
            ->assertSee('Reason: Blurry scan')
            ->assertSee('Browse Files')
            ->assertSee('No file selected')
            ->assertDontSee('>Resubmit<', false)
            ->getContent();

        $this->assertSame(1, substr_count($rejectedPage, 'Upload Replacement'));
        $cardEnd = strpos($rejectedPage, 'documents-page-grid');
        $cardMarkup = substr($rejectedPage, 0, $cardEnd === false ? null : $cardEnd);
        $this->assertStringNotContainsString('doc-replace-form', $cardMarkup);
        $this->assertStringNotContainsString('Browse Files', $cardMarkup);
        $this->assertStringNotContainsString('Upload Replacement', $cardMarkup);
        $this->assertStringContainsString('accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"', $rejectedPage);

        $otherScholar = User::register([
            'full_name' => 'Other Document Scholar',
            'scholar_id' => 'SCH-DOC-OTHER',
            'email' => 'doc-other-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $scholar->scholarship_program_id,
            'scholarship_club_id' => $scholar->scholarship_club_id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($otherScholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $birth->id,
                'file' => UploadedFile::fake()->create('stolen.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame('rejected', $document->status);
        $this->assertSame('birth.pdf', $document->original_name);
        $this->assertSame(0, $document->revisions()->count());

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $birth->id,
                'file' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
            ])
            ->assertRedirect(route('user.documents'))
            ->assertSessionHasErrors('file');

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $birth->id,
                'file' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'),
            ])
            ->assertRedirect(route('user.documents'))
            ->assertSessionHasErrors('file');

        $document->refresh();
        $this->assertSame('rejected', $document->status);
        $this->assertSame('birth.pdf', $document->original_name);

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $birth->id,
                'file' => UploadedFile::fake()->create('clear-copy.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('user.documents'))
            ->assertSessionHas('success', 'Replacement submitted. It is pending Scholar Staff review.');

        $document->refresh();
        $revision = $document->revisions()->first();
        $this->assertSame('pending', $document->status);
        $this->assertSame('clear-copy.pdf', $document->original_name);
        $this->assertNull($document->review_notes);
        $this->assertNotNull($document->uploaded_at);
        $this->assertNotSame('documents/birth.pdf', $document->file_path);
        Storage::disk('public')->assertExists('documents/birth.pdf');
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertSame('rejected', $revision->status);
        $this->assertSame('Blurry scan', $revision->review_notes);
        $this->assertSame('documents/birth.pdf', $revision->file_path);
        $this->assertSame('birth.pdf', $revision->original_name);

        $this->assertDatabaseHas('scholar_notifications', [
            'user_id' => $staff->id,
            'title' => 'Replacement document submitted',
        ]);
        $this->assertDatabaseMissing('scholar_notifications', [
            'user_id' => $otherStaff->id,
            'title' => 'Replacement document submitted',
        ]);

        $pendingPage = $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertSee('Previous rejection: Blurry scan')
            ->assertDontSee('>Upload Replacement<', false);
        $this->assertSame(1, $pendingPage->viewData('documentOverview')['pending']);
        $this->assertSame(0, $pendingPage->viewData('documentOverview')['rejected']);

        $this->actingAs($staff)
            ->patch(route('staff.documents.status', $document), [
                'status' => 'rejected',
                'review_notes' => 'Still blurry',
            ])
            ->assertRedirect();

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $birth->id,
                'file' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame('pending', $document->status);
        $this->assertSame('photo.jpg', $document->original_name);
        $this->assertSame(2, $document->revisions()->count());
        $this->assertSame('clear-copy.pdf', $document->revisions()->orderByDesc('id')->first()->original_name);

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $birth->id,
                'file' => UploadedFile::fake()->image('photo.png'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $document->refresh();
        $this->assertSame('pending', $document->status);
        $this->assertSame('photo.jpg', $document->original_name);

        $this->actingAs($staff)
            ->patch(route('staff.documents.status', $document), [
                'status' => 'rejected',
                'review_notes' => 'Use a PNG',
            ])
            ->assertRedirect();

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $birth->id,
                'file' => UploadedFile::fake()->image('photo.png'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame('pending', $document->status);
        $this->assertSame('photo.png', $document->original_name);
        $this->assertSame(3, $document->revisions()->count());

        $this->actingAs($staff)
            ->patch(route('staff.documents.status', $document), ['status' => 'approved'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame('approved', $document->status);
        $this->assertSame('photo.png', $document->original_name);
        $this->assertSame(3, $document->revisions()->count());

        $approvedPage = $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertSee('View')
            ->assertSee('Download')
            ->assertDontSee('>Upload Replacement<', false);
        $this->assertSame(1, $approvedPage->viewData('documentOverview')['approved']);
        $this->assertSame(0, $approvedPage->viewData('documentOverview')['pending']);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: DocumentType, 4: DocumentType, 5: Document}
     */
    private function makeDocuments(): array
    {
        $program = $this->makeProgram('doc-submissions');
        $otherProgram = $this->makeProgram('doc-submissions-other');
        $club = ScholarshipClub::createForProgram('Document Club', $program->id);
        $otherClub = ScholarshipClub::createForProgram('Other Document Club', $otherProgram->id);

        $staff = $this->makeStaff($program, $club, 'Document Staff', 'doc-staff@example.com', 'STAFF-DOC-SUB');
        $otherStaff = $this->makeStaff($otherProgram, $otherClub, 'Other Staff', 'doc-other-staff@example.com', 'STAFF-DOC-OTHER');
        $scholar = User::register([
            'full_name' => 'Document Scholar',
            'scholar_id' => 'SCH-DOC-SUB',
            'email' => 'doc-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $birth = DocumentType::create([
            'name' => 'Birth Certificate',
            'slug' => 'birth-certificate-submissions',
            'description' => 'HEHE',
            'required' => true,
            'scholarship_program_id' => $program->id,
        ]);
        $studentId = DocumentType::create([
            'name' => 'Student ID',
            'slug' => 'student-id-submissions',
            'description' => 'Current student ID',
            'required' => true,
            'scholarship_program_id' => $program->id,
        ]);

        $document = Document::create([
            'user_id' => $scholar->id,
            'document_type_id' => $birth->id,
            'status' => 'pending',
            'file_path' => 'documents/birth.pdf',
            'original_name' => 'birth.pdf',
            'uploaded_at' => now(),
        ]);

        return [$staff, $otherStaff, $scholar, $birth, $studentId, $document];
    }

    private function makeProgram(string $slug): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function makeStaff(ScholarshipProgram $program, ScholarshipClub $club, string $name, string $email, string $scholarId): User
    {
        return User::register([
            'full_name' => $name,
            'scholar_id' => $scholarId,
            'email' => $email,
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);
    }
}
