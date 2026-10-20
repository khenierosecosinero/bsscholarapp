<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\ScholarNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentDeadlineAndSchoolFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_staff_can_add_and_edit_a_document_type_without_changing_submissions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 17:00:00', config('app.timezone')));
        [$staff, $scholar, $type, $document] = $this->makeScholarDocument();

        $this->actingAs($staff)
            ->post(route('staff.documents.store'), [
                'name' => 'Good Moral Certificate',
                'description' => 'Latest certificate',
                'required' => 1,
                'submission_deadline' => '2026-11-01T08:00',
            ])
            ->assertRedirect();

        $created = DocumentType::query()->where('name', 'Good Moral Certificate')->first();
        $this->assertNotNull($created);
        $this->assertSame('Latest certificate', $created->description);
        $this->assertTrue($created->acceptsSubmissions());
        $this->assertSame(1, Document::query()->where('user_id', $scholar->id)->where('document_type_id', $created->id)->count());

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertSee('Good Moral Certificate')
            ->assertSee('Nov 1, 2026 8:00 AM')
            ->assertSee('No deadline set')
            ->assertDontSee('Submissions are currently closed');

        $edit = $this->actingAs($staff)
            ->get(route('staff.documents.edit', $type))
            ->assertOk()
            ->assertSee('Edit Birth Certificate', false);
        $editHtml = $edit->getContent();
        $backLink = strpos($editHtml, 'staff-doc-back-link');
        $heading = strpos($editHtml, '<h2>Edit Birth Certificate</h2>');
        $this->assertNotFalse($backLink);
        $this->assertNotFalse($heading);
        $this->assertLessThan($heading, $backLink);
        $this->assertStringContainsString(route('staff.documents.show', $type), $editHtml);
        $this->assertSame(1, substr_count($editHtml, 'staff-doc-back-link'));

        $this->actingAs($staff)
            ->put(route('staff.documents.update', $type), [
                'name' => 'Birth Certificate Copy',
                'description' => 'Updated instructions',
                'required' => 1,
                'submission_deadline' => '2026-10-25T09:30',
            ])
            ->assertRedirect(route('staff.documents.show', $type));

        $type->refresh();
        $document->refresh();
        $this->assertSame('Birth Certificate Copy', $type->name);
        $this->assertSame('Updated instructions', $type->description);
        $this->assertSame('documents/birth.pdf', $document->file_path);
        $this->assertSame('pending', $document->status);
        $this->assertSame('birth.pdf', $document->original_name);
        $this->assertDatabaseHas('scholar_notifications', [
            'user_id' => $scholar->id,
            'title' => 'Deadline set: Birth Certificate Copy',
        ]);
    }

    public function test_deadlines_and_manual_closure_are_enforced_and_notified_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 17:00:00', config('app.timezone')));
        [$staff, $scholar, $type, $document] = $this->makeScholarDocument();

        $type->update(['submission_deadline' => Carbon::parse('2026-10-21 05:00:00', config('app.timezone'))]);

        $this->actingAs($scholar)->get(route('user.documents'))->assertOk();
        $this->actingAs($scholar)->get(route('user.notifications'))->assertOk()->assertSee('View Documents');

        $this->assertSame(1, ScholarNotification::query()->where('user_id', $scholar->id)->where('title', 'Deadline approaching: Birth Certificate')->count());

        Carbon::setTestNow(Carbon::parse('2026-10-21 06:00:00', config('app.timezone')));

        $this->actingAs($scholar)->get(route('user.documents'))->assertOk()->assertDontSee('Submissions are currently closed');
        $this->actingAs($scholar)->get(route('user.documents'))->assertOk();

        $type->refresh();
        $this->assertFalse($type->submissionsAreOpen());
        $this->assertFalse($type->acceptsSubmissions());
        $this->assertSame(1, ScholarNotification::query()->where('user_id', $scholar->id)->where('title', 'Deadline passed: Birth Certificate')->count());

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $type->id,
                'file' => UploadedFile::fake()->create('late.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('user.documents'))
            ->assertSessionHas('error');

        $document->refresh();
        $this->assertSame('documents/birth.pdf', $document->file_path);
        $this->assertSame('pending', $document->status);

        $this->actingAs($staff)
            ->post(route('staff.documents.submissions.open', $type))
            ->assertRedirect()
            ->assertSessionHas('success');

        $type->refresh();
        $this->assertTrue($type->submissionsAreOpen());
        $this->assertTrue($type->deadlineHasPassed());
        $this->assertFalse($type->acceptsSubmissions());

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertDontSee('UPLOAD NEW DOCUMENT')
            ->assertDontSee("value='".$type->id."'", false);

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $type->id,
                'file' => UploadedFile::fake()->create('reopened-late.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('user.documents'))
            ->assertSessionHas('error', 'The submission deadline for Birth Certificate has passed.');

        $document->refresh();
        $this->assertSame('documents/birth.pdf', $document->file_path);

        $this->actingAs($staff)
            ->put(route('staff.documents.update', $type), [
                'name' => 'Birth Certificate',
                'description' => 'HEHE',
                'required' => 1,
                'submission_deadline' => '2026-10-28T17:00',
            ])
            ->assertRedirect();

        $type->refresh();
        $this->assertTrue($type->acceptsSubmissions());
        $this->assertDatabaseHas('scholar_notifications', [
            'user_id' => $scholar->id,
            'title' => 'Deadline extended: Birth Certificate',
        ]);

        $this->actingAs($staff)
            ->post(route('staff.documents.submissions.close', $type))
            ->assertRedirect();

        $type->refresh();
        $this->assertFalse($type->acceptsSubmissions());

        $this->actingAs($scholar)
            ->from(route('user.documents'))
            ->post(route('user.documents.upload'), [
                'document_type_id' => $type->id,
                'file' => UploadedFile::fake()->create('closed.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHas('error');

        $document->refresh();
        $this->assertSame('birth.pdf', $document->original_name);

        $this->actingAs($staff)
            ->post(route('staff.documents.submissions.open', $type))
            ->assertRedirect();

        $type->refresh();
        $this->assertTrue($type->acceptsSubmissions());
        $this->assertSame(2, ScholarNotification::query()->where('user_id', $scholar->id)->where('title', 'Submissions reopened: Birth Certificate')->count());

        $this->actingAs($staff)
            ->put(route('staff.documents.update', $type), [
                'name' => 'Birth Certificate',
                'description' => 'HEHE',
                'required' => 1,
                'submission_deadline' => '',
            ])
            ->assertRedirect();

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertSee('No deadline set');
    }

    public function test_school_filter_limits_submissions_and_keeps_other_filters(): void
    {
        [$staff, $sti, $snsu, $stiScholar, $snsuScholar, $unassigned, $type] = $this->makeSchoolDocuments();

        $this->actingAs($staff)
            ->get(route('staff.documents.show', $type))
            ->assertOk()
            ->assertSee('All Schools/Universities')
            ->assertSee('No School Specified')
            ->assertSee($sti->name)
            ->assertSee($snsu->name)
            ->assertSee($stiScholar->full_name)
            ->assertSee($snsuScholar->full_name)
            ->assertSee($unassigned->full_name)
            ->assertSee('3 submitted');

        $stiPage = $this->actingAs($staff)
            ->get(route('staff.documents.show', [
                'documentType' => $type->id,
                'school' => 'id:'.$sti->id,
                'status' => 'submitted',
                'search' => 'STI',
            ]))
            ->assertOk()
            ->assertSee($stiScholar->full_name)
            ->assertDontSee($snsuScholar->full_name)
            ->assertDontSee($unassigned->full_name)
            ->assertSee('1 submitted');

        $this->assertStringContainsString('school=id%3A'.$sti->id, $stiPage->getContent());

        $this->actingAs($staff)
            ->get(route('staff.documents.show', [
                'documentType' => $type->id,
                'school' => 'unassigned',
                'status' => 'pending',
            ]))
            ->assertOk()
            ->assertSee($unassigned->full_name)
            ->assertDontSee($stiScholar->full_name)
            ->assertSee('1 pending')
            ->assertSee('1 submitted');

        $html = $this->actingAs($staff)
            ->get(route('staff.documents.show', $type))
            ->assertOk()
            ->getContent();

        $link = strpos($html, 'staff-doc-back-link');
        $heading = strpos($html, '<h2>'.$type->name.'</h2>');
        $this->assertNotFalse($link);
        $this->assertNotFalse($heading);
        $this->assertLessThan($heading, $link);
        $this->assertStringContainsString(route('staff.documents'), $html);
    }

    /**
     * @return array{0: User, 1: User, 2: DocumentType, 3: Document}
     */
    private function makeScholarDocument(): array
    {
        $program = $this->makeProgram('doc-deadline');
        $club = ScholarshipClub::createForProgram('Deadline Club', $program->id);
        $staff = $this->makeStaff($program, $club, 'Deadline Staff', 'deadline-staff@example.com', 'STAFF-DEADLINE');
        $scholar = User::register([
            'full_name' => 'Deadline Scholar',
            'scholar_id' => 'SCH-DEADLINE',
            'email' => 'deadline-scholar@example.com',
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
            'slug' => 'birth-certificate-deadline',
            'description' => 'HEHE',
            'required' => true,
            'scholarship_program_id' => $program->id,
            'submissions_open' => true,
        ]);
        $document = Document::create([
            'user_id' => $scholar->id,
            'document_type_id' => $type->id,
            'status' => 'pending',
            'file_path' => 'documents/birth.pdf',
            'original_name' => 'birth.pdf',
            'uploaded_at' => now(),
        ]);

        return [$staff, $scholar, $type, $document];
    }

    /**
     * @return array{0: User, 1: ScholarshipClubSchool, 2: ScholarshipClubSchool, 3: User, 4: User, 5: User, 6: DocumentType}
     */
    private function makeSchoolDocuments(): array
    {
        $program = $this->makeProgram('doc-school-filter');
        $club = ScholarshipClub::createForProgram('School Docs Club', $program->id);
        $sti = ScholarshipClubSchool::createForClub('STI College Surigao', $club->id);
        $snsu = ScholarshipClubSchool::createForClub('Surigao del Norte State University', $club->id);
        $staff = $this->makeStaff($program, $club, 'School Docs Staff', 'school-docs-staff@example.com', 'STAFF-DOC-SCHOOL');

        $stiScholar = $this->makeScholar($program, $club, $sti, 'STI Scholar', 'sti-doc');
        $snsuScholar = $this->makeScholar($program, $club, $snsu, 'SNSU Scholar', 'snsu-doc');
        $unassigned = User::register([
            'full_name' => 'No School Scholar',
            'scholar_id' => 'SCH-DOC-NONE',
            'email' => 'doc-no-school@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'school_university' => null,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $type = DocumentType::create([
            'name' => 'Student ID',
            'slug' => 'student-id-school-filter',
            'description' => 'Current ID',
            'required' => true,
            'scholarship_program_id' => $program->id,
            'submissions_open' => true,
        ]);

        foreach ([$stiScholar, $snsuScholar, $unassigned] as $scholar) {
            Document::create([
                'user_id' => $scholar->id,
                'document_type_id' => $type->id,
                'status' => 'pending',
                'file_path' => 'documents/'.$scholar->id.'.pdf',
                'original_name' => 'id.pdf',
                'uploaded_at' => now(),
            ]);
        }

        return [$staff, $sti, $snsu, $stiScholar, $snsuScholar, $unassigned, $type];
    }

    private function makeScholar(ScholarshipProgram $program, ScholarshipClub $club, ScholarshipClubSchool $school, string $name, string $suffix): User
    {
        return User::register([
            'full_name' => $name,
            'scholar_id' => 'SCH-DOC-'.strtoupper($suffix),
            'email' => $suffix.'@example.com',
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
