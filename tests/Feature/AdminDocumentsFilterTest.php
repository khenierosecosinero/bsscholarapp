<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Support\PhilippineIslandGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDocumentsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_page_is_unified_without_city_or_province_scholar_split(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.documents'))
            ->assertOk()
            ->assertSee('All Regions')
            ->assertSee('Luzon')
            ->assertSee('Visayas')
            ->assertSee('Mindanao')
            ->assertSee('All Scholarship Clubs')
            ->assertSee('Province')
            ->assertSee('Municipality / City')
            ->assertSee('Document Submissions')
            ->assertDontSee('All Locations')
            ->assertDontSee('admin-location-pill', false)
            ->assertDontSee('Scholarship category')
            ->assertDontSee('>City Scholar</option>', false)
            ->assertDontSee('>Province Scholar</option>', false)
            ->assertDontSee('Viewing: City Scholar')
            ->assertDontSee('City Scholar and Province Scholar stay separate')
            ->assertDontSee('Current Admin Scope')
            ->assertDontSee('Showing City Scholar records')
            ->assertDontSee('City Scholar — All Locations')
            ->assertDontSee('No registered Scholarship Club found')
            ->assertDontSee('>Scholar Program</th>', false)
            ->assertDontSee('>Program Type</th>', false)
            ->assertSee('No documents found');
    }

    public function test_documents_list_includes_city_and_province_program_scholars(): void
    {
        $province = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-docs-dir', null, 'Caraga');
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-docs-dir', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('Dapa Scholars Club', $city->id);

        $cityScholar = $this->makeScholar($city, $club, 'CITY');
        $provinceScholar = $this->makeScholar($province, null, 'PROV');
        $this->makeDocument($cityScholar, $city, 'Latest Grade');
        $this->makeDocument($provinceScholar, $province, 'Latest Grade');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.documents'))
            ->assertOk()
            ->assertSee($cityScholar->full_name)
            ->assertSee($provinceScholar->full_name)
            ->assertSee('Dapa Scholars Club');
    }

    public function test_region_province_city_and_club_filters_limit_documents(): void
    {
        $sdn = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-docs-loc', null, 'Caraga');
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-docs-loc', 'Surigao del Norte', 'Caraga');
        $surigao = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-docs-loc', 'Surigao del Norte', 'Caraga');
        $manila = $this->makeProgram('city_municipality', 'Manila', 'Manila, Metro Manila', 'manila-docs-loc', 'Metro Manila', 'National Capital Region');
        $this->makeProgram('province', 'Metro Manila', 'Metro Manila', 'mm-docs-loc', null, 'National Capital Region');

        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $surigao->id);
        $dapaClub = ScholarshipClub::createForProgram('Dapa Scholars Club', $dapa->id);
        $manilaClub = ScholarshipClub::createForProgram('Manila Scholars Club', $manila->id);

        $keniScholar = $this->makeScholar($surigao, $keni, 'KENI');
        $dapaScholar = $this->makeScholar($dapa, $dapaClub, 'DAPA');
        $luzonScholar = $this->makeScholar($manila, $manilaClub, 'LUZON');
        $provinceScholar = $this->makeScholar($sdn, null, 'SDN');

        $this->makeDocument($keniScholar, $surigao, 'KENI Grade');
        $this->makeDocument($dapaScholar, $dapa, 'Dapa Grade');
        $this->makeDocument($luzonScholar, $manila, 'Manila Grade');
        $this->makeDocument($provinceScholar, $sdn, 'Province Grade');

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.documents', ['region' => PhilippineIslandGroup::LUZON]))
            ->assertOk()
            ->assertSee($luzonScholar->full_name)
            ->assertDontSee($keniScholar->full_name)
            ->assertDontSee($dapaScholar->full_name);

        $this->actingAs($admin)
            ->get(route('admin.documents', ['region' => PhilippineIslandGroup::MINDANAO]))
            ->assertOk()
            ->assertSee($keniScholar->full_name)
            ->assertSee($dapaScholar->full_name)
            ->assertSee($provinceScholar->full_name)
            ->assertDontSee($luzonScholar->full_name);

        $this->actingAs($admin)
            ->get(route('admin.documents', ['location' => $dapa->id]))
            ->assertOk()
            ->assertSee($dapaScholar->full_name)
            ->assertDontSee($keniScholar->full_name)
            ->assertDontSee($luzonScholar->full_name);

        $this->actingAs($admin)
            ->get(route('admin.documents', ['club' => $keni->id]))
            ->assertOk()
            ->assertSee($keniScholar->full_name)
            ->assertDontSee($dapaScholar->full_name)
            ->assertDontSee($luzonScholar->full_name)
            ->assertSee('data-province="Surigao del Norte"', false)
            ->assertSee('data-city="Surigao City"', false)
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('Dapa Scholars Club');

        $this->actingAs($admin)
            ->get(route('admin.documents', [
                'location' => $dapa->id,
                'club' => $keni->id,
            ]))
            ->assertOk()
            ->assertDontSee($keniScholar->full_name)
            ->assertDontSee($dapaScholar->full_name)
            ->assertSee('No documents found');
    }

    public function test_documents_search_works_with_filters(): void
    {
        $city = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-docs-search', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $match = $this->makeScholar($city, $club, 'MATCH');
        $other = $this->makeScholar($city, $club, 'OTHER');
        $this->makeDocument($match, $city, 'Match Grade');
        $this->makeDocument($other, $city, 'Other Grade');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.documents', ['search' => $match->scholar_id]))
            ->assertOk()
            ->assertSee($match->full_name)
            ->assertDontSee($other->full_name);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-DOCS-DIR-001',
            'email' => 'admin-docs-dir@example.com',
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

    private function makeScholar(ScholarshipProgram $program, ?ScholarshipClub $club, string $suffix): User
    {
        return User::register([
            'full_name' => 'Docs Scholar '.$suffix,
            'scholar_id' => 'SCH-DOC-'.$suffix,
            'email' => 'docs-scholar-'.strtolower($suffix).'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club?->id,
            'city' => $program->isCityOrMunicipality() ? $program->location_name : null,
            'province' => $program->isProvince() ? $program->location_name : $program->province_name,
        ]);
    }

    private function makeDocument(User $scholar, ScholarshipProgram $program, string $typeName): Document
    {
        $type = DocumentType::create([
            'name' => $typeName,
            'slug' => 'doc-'.strtolower(str_replace(' ', '-', $typeName)).'-'.$program->id.'-'.$scholar->id,
            'scholarship_program_id' => $program->id,
            'required' => true,
        ]);

        return Document::create([
            'user_id' => $scholar->id,
            'document_type_id' => $type->id,
            'status' => 'approved',
            'file_path' => 'documents/'.$scholar->id.'/file.pdf',
            'original_name' => 'file.pdf',
            'uploaded_at' => now(),
        ]);
    }
}
