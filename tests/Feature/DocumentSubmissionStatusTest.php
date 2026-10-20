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
