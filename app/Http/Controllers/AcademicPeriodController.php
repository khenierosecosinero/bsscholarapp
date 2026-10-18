<?php

namespace App\Http\Controllers;

use App\Services\AcademicSettingsService;
use Illuminate\Http\Request;

class AcademicPeriodController extends Controller
{
    public function update(Request $request, AcademicSettingsService $academic)
    {
        $validated = $request->validate([
            'year_start' => ['required', 'integer'],
            'semester' => ['required', 'in:1st Semester,2nd Semester'],
        ]);

        $period = $academic->rememberSelectedPeriod((int) $validated['year_start'], $validated['semester']);
        $message = 'Showing '.$period->semester.' AY '.$period->year_start.'–'.$period->year_end.'. Saved records were not changed.';

        $previous = (string) url()->previous();

        if ($request->user()?->isScholar() && str_contains($previous, '/user/profile')) {
            return redirect()
                ->route('user.profile', ['tab' => 'academic-settings'])
                ->with('success', $message);
        }

        if ($request->user()?->isScholarStaff() && str_contains($previous, '/staff/settings')) {
            return redirect()
                ->route('staff.settings')
                ->with('success', $message);
        }

        return back()->with('success', $message);
    }
}
