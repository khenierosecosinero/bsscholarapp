<?php

namespace Tests\Feature;

use App\Models\AdminSectionView;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarNotificationBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_badges_clear_per_admin_and_return_only_for_new_items(): void
    {
        $admin = $this->makeAdmin('first');
        $otherAdmin = $this->makeAdmin('second');
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-badges',
            'is_active' => true,
        ]);

        $seenAt = now()->subDay();
        $scholar = $this->makeScholar($program, 'OLD', User::STATUS_PENDING);
        $scholar->forceFill(['created_at' => $seenAt, 'updated_at' => $seenAt])->save();
        $document = $this->makePendingDocument($scholar, $program);
        $document->forceFill(['uploaded_at' => $seenAt, 'created_at' => $seenAt, 'updated_at' => $seenAt])->save();

        $this->actingAs($admin)
            ->getJson(route('admin.sidebar-badges'))
            ->assertOk()
            ->assertJsonPath('dashboard', 2)
            ->assertJsonPath('scholars', 1)
            ->assertJsonPath('documents', 1)
            ->assertJsonPath('staff', 0);

        $this->assertSame(0, AdminSectionView::query()->count());

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-admin-badge="dashboard"', false)
            ->assertSee('data-admin-badge="scholars"', false)
            ->assertSee('data-admin-badge="documents"', false);

        $this->actingAs($admin)
            ->getJson(route('admin.sidebar-badges'))
            ->assertOk()
            ->assertJsonPath('dashboard', 0)
            ->assertJsonPath('scholars', 1)
            ->assertJsonPath('documents', 1);

        auth()->logout();
        $this->flushSession();

        $this->actingAs($admin)
            ->getJson(route('admin.sidebar-badges'))
            ->assertOk()
            ->assertJsonPath('dashboard', 0)
            ->assertJsonPath('scholars', 1)
            ->assertJsonPath('documents', 1);

        $this->actingAs($admin)
            ->get(route('admin.scholars'))
            ->assertOk()
            ->assertDontSee('data-admin-badge="scholars"', false)
            ->assertSee('data-admin-badge="documents"', false);

        $this->actingAs($admin)
            ->getJson(route('admin.sidebar-badges'))
            ->assertJsonPath('scholars', 0)
            ->assertJsonPath('documents', 1)
            ->assertJsonPath('dashboard', 0);

        $this->travel(2)->seconds();
        $this->makeScholar($program, 'NEW', User::STATUS_PENDING);

        $this->actingAs($admin)
            ->getJson(route('admin.sidebar-badges'))
            ->assertOk()
            ->assertJsonPath('scholars', 1)
            ->assertJsonPath('documents', 1)
            ->assertJsonPath('dashboard', 1);

        $this->actingAs($otherAdmin)
            ->getJson(route('admin.sidebar-badges'))
            ->assertOk()
            ->assertJsonPath('scholars', 2)
            ->assertJsonPath('documents', 1)
            ->assertJsonPath('dashboard', 3);

        $this->assertSame(0, AdminSectionView::query()->where('user_id', $otherAdmin->id)->count());
    }

    private function makeAdmin(string $suffix): User
    {
        $admin = User::register([
            'full_name' => 'Badge Admin '.$suffix,
            'scholar_id' => 'ADMIN-BADGE-'.strtoupper($suffix),
            'email' => 'admin-badge-'.$suffix.'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    private function makeScholar(ScholarshipProgram $program, string $suffix, string $status): User
    {
        return User::register([
            'full_name' => 'Badge Scholar '.$suffix,
            'scholar_id' => 'SCH-BADGE-'.$suffix,
            'email' => 'scholar-badge-'.strtolower($suffix).'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => $status,
            'scholarship_program_id' => $program->id,
            'city' => $program->location_name,
            'province' => $program->province_name,
        ]);
    }

    private function makePendingDocument(User $scholar, ScholarshipProgram $program): Document
    {
        $type = DocumentType::create([
            'name' => 'Badge Requirement',
            'slug' => 'badge-requirement-'.$program->id,
            'scholarship_program_id' => $program->id,
            'required' => true,
        ]);

        return Document::create([
            'user_id' => $scholar->id,
            'document_type_id' => $type->id,
            'status' => 'pending',
            'file_path' => 'documents/'.$scholar->id.'/badge.pdf',
            'original_name' => 'badge.pdf',
            'uploaded_at' => now()->subDay(),
        ]);
    }
}
