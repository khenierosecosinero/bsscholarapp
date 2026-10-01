<?php

namespace App\Http\Controllers;

use App\Models\ScholarshipClubSchool;
use App\Services\StaffDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffSchoolController extends Controller
{
    public function __construct(private StaffDashboardService $staffData) {}

    public function create()
    {
        $staff = $this->staffWithClub();

        return view('staff.schools.create', $this->staffData->layoutPayload(
            $staff,
            'settings',
            'Add School/University',
            'Add a School/University scholars can select for '.$staff->scholarshipClubName().'.',
            'Settings'
        ));
    }

    public function store(Request $request)
    {
        $staff = $this->staffWithClub();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Please enter a School/University name.',
        ]);

        ScholarshipClubSchool::createForClub(
            $validated['name'],
            (int) $staff->scholarship_club_id,
            $staff->id
        );

        return redirect()
            ->route('staff.settings')
            ->with('success', 'School/University added to your Scholarship Club.');
    }

    public function edit(ScholarshipClubSchool $school)
    {
        $staff = $this->staffWithClub();
        $this->assertOwnsSchool($staff, $school);

        return view('staff.schools.edit', array_merge(
            $this->staffData->layoutPayload(
                $staff,
                'settings',
                'Edit School/University',
                'Update this School/University for '.$staff->scholarshipClubName().'.',
                'Settings'
            ),
            compact('school')
        ));
    }

    public function update(Request $request, ScholarshipClubSchool $school)
    {
        $staff = $this->staffWithClub();
        $this->assertOwnsSchool($staff, $school);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Please enter a School/University name.',
        ]);

        $school->rename($validated['name']);

        return redirect()
            ->route('staff.settings')
            ->with('success', 'School/University name updated.');
    }

    public function destroy(ScholarshipClubSchool $school)
    {
        $staff = $this->staffWithClub();
        $this->assertOwnsSchool($staff, $school);

        $school->delete();

        return redirect()
            ->route('staff.settings')
            ->with('success', 'School/University removed from your Scholarship Club.');
    }

    private function staffWithClub()
    {
        $staff = Auth::user()->load('scholarshipClub');

        abort_unless(
            $staff->scholarship_club_id,
            403,
            'Save your Scholarship Club in Settings before managing School/University names.'
        );

        return $staff;
    }

    private function assertOwnsSchool($staff, ScholarshipClubSchool $school): void
    {
        abort_unless(
            (int) $school->scholarship_club_id === (int) $staff->scholarship_club_id,
            403,
            'You can only manage School/University names for your Scholarship Club.'
        );
    }
}
