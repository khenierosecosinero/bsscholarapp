<?php

namespace Tests\Feature;

use App\Models\GoogleDriveConnection;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDriveFolderStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_for_scholar_uses_registered_province_and_municipality(): void
    {
        $scholar = $this->makeScholar();

        $location = app(GoogleDriveService::class)->locationForScholar($scholar);

        $this->assertSame('Surigao del Norte', $location['province']);
        $this->assertSame('Surigao City', $location['municipality']);
    }

    public function test_student_folder_name_uses_en_dash_between_code_and_name(): void
    {
        $name = app(GoogleDriveService::class)->studentFolderName('BSSA-2026-001', 'Juan Dela Cruz');

        $this->assertSame('BSSA-2026-001 – Juan Dela Cruz', $name);
    }

    public function test_drive_year_folder_names_use_ay_prefix_and_en_dash(): void
    {
        $drive = app(GoogleDriveService::class);

        $this->assertSame('AY 2026–2027', $drive->driveYearFolderName(2026, 2027));
        $this->assertSame('AY 2026–2027', $drive->normalizeYearFolderLabel('2026-2027'));
        $this->assertSame('AY 2026–2027', $drive->normalizeYearFolderLabel('AY 2026–2027'));
        $this->assertSame('AY 2025–2026', $drive->normalizeYearFolderLabel('2025–2026'));
    }

    public function test_documents_page_omits_student_information_card_and_drive_disconnected_banner(): void
    {
        $scholar = $this->makeScholar();

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertDontSee('STUDENT INFORMATION')
            ->assertDontSee('Uploads unavailable')
            ->assertDontSee('Google Drive is not connected yet');
    }

    public function test_documents_page_still_loads_when_org_token_exists(): void
    {
        $scholar = $this->makeScholar();

        GoogleDriveConnection::query()->create([
            'token' => [
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'expires_in' => 3600,
                'created' => time(),
            ],
            'connected_by' => null,
        ]);

        $this->actingAs($scholar)
            ->get(route('user.documents'))
            ->assertOk()
            ->assertDontSee('STUDENT INFORMATION')
            ->assertDontSee('Google Drive is not connected yet');
    }

    public function test_store_token_preserves_existing_refresh_token(): void
    {
        $drive = app(GoogleDriveService::class);

        $drive->storeToken([
            'access_token' => 'first-access',
            'refresh_token' => 'keep-me',
            'expires_in' => 3600,
            'created' => time(),
        ], null);

        $drive->storeToken([
            'access_token' => 'second-access',
            'expires_in' => 3600,
            'created' => time(),
        ], null);

        $stored = GoogleDriveConnection::query()->first()?->token;

        $this->assertSame('second-access', $stored['access_token'] ?? null);
        $this->assertSame('keep-me', $stored['refresh_token'] ?? null);
        $this->assertTrue($drive->isConnected());
    }

    private function makeScholar(string $province = 'Surigao del Norte', string $city = 'Surigao City'): User
    {
        $program = ScholarshipProgram::create([
            'location_name' => $city,
            'location_type' => 'city_municipality',
            'name' => $city.', '.$province,
            'display_name' => $city.', '.$province,
            'province_name' => $province,
            'slug' => strtolower(str_replace(' ', '-', $city)).'-drive-structure-'.uniqid(),
            'is_active' => true,
        ]);

        $club = ScholarshipClub::create([
            'name' => 'Drive Structure Club',
            'city' => $city,
            'province' => $province,
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);

        return User::register([
            'full_name' => 'Juan Dela Cruz',
            'scholar_id' => 'BSSA-2026-001',
            'email' => 'drive-structure-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => $city,
            'province' => $province,
        ]);
    }
}
