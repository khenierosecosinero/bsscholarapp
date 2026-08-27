<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use App\Services\AcademicSettingsService;
use App\Services\ScholarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileActionController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private AcademicSettingsService $academic,
        private AccountService $accounts,
    ) {}

    /**
     * Update non-credential profile fields only.
     * Email, scholar ID, and password cannot be changed here.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'cellphone_number' => 'nullable|string|max:50',
            'school_university' => 'nullable|string|max:255',
            'course_year_level' => 'nullable|string|max:255',
            'year_level' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date|before:today',
        ]);

        $user->update($data);
        $this->scholar->logActivity($user, 'profile', 'Profile information updated');

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updateGuardian(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'guardian_name' => 'nullable|string|max:255',
            'guardian_relationship' => 'nullable|string|max:100',
            'guardian_cellphone' => 'nullable|string|max:50',
        ]);

        $user->update($data);
        $this->scholar->logActivity($user, 'profile', 'Guardian information updated');

        return back()->with('success', 'Guardian information saved.');
    }

    public function updateAcademicPreference(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'year_start' => 'required|integer|min:2000|max:2100',
            'semester' => 'required|in:1st Semester,2nd Semester',
        ]);

        $this->academic->updateUserPreference($user, $validated);
        $this->scholar->logActivity($user, 'profile', 'Academic period preference updated', $validated);
        $this->scholar->notify(
            $user,
            'Academic Period Updated',
            "Your records now reflect {$validated['semester']} of {$validated['year_start']}-".($validated['year_start'] + 1).'.',
            'system'
        );

        return back()->with('success', 'Your academic period has been updated. Service hours and progress now reflect the selected semester.');
    }

    public function updateGlobalAcademicSettings(Request $request)
    {
        $user = Auth::user();

        if (! $user->isAdmin()) {
            abort(403, 'Only administrators can update system-wide academic settings.');
        }

        $validated = $request->validate([
            'year_start' => 'required|integer|min:2000|max:2100',
            'semester' => 'required|in:1st Semester,2nd Semester',
        ]);

        $setting = $this->academic->updateGlobal($validated, $user);
        $this->scholar->logActivity($user, 'system', 'System academic period updated', $validated);

        return back()->with('success', "System academic period set to {$setting->semester} {$setting->year_start}-{$setting->year_end}. New attendance records will use this period.");
    }

    public function changePassword(Request $request)
    {
        $this->ensurePasswordChangeIsNotRateLimited($request);

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ]);

        $user = Auth::user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            RateLimiter::hit($this->passwordThrottleKey($request), 300);

            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->updatePassword($validated['password']);

        RateLimiter::clear($this->passwordThrottleKey($request));
        $request->session()->regenerate();

        $this->scholar->logActivity($user, 'security', 'Password changed');
        $this->scholar->notify($user, 'Password Changed', 'Your account password was updated successfully.', 'system', true);

        return back()->with('success', 'Password changed successfully.');
    }

    public function destroy(Request $request)
    {
        $this->ensureDeleteIsNotRateLimited($request);

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        if (! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($this->deleteThrottleKey($request), 300);

            throw ValidationException::withMessages([
                'password' => 'Password is incorrect.',
            ]);
        }

        RateLimiter::clear($this->deleteThrottleKey($request));

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->accounts->permanentlyDelete($user);

        return redirect()->route('login')->with('success', 'Your account has been deleted.');
    }

    private function passwordThrottleKey(Request $request): string
    {
        return 'password-change|'.Auth::id().'|'.$request->ip();
    }

    private function deleteThrottleKey(Request $request): string
    {
        return 'account-delete|'.Auth::id().'|'.$request->ip();
    }

    private function ensurePasswordChangeIsNotRateLimited(Request $request): void
    {
        if (RateLimiter::tooManyAttempts($this->passwordThrottleKey($request), 5)) {
            $seconds = RateLimiter::availableIn($this->passwordThrottleKey($request));

            throw ValidationException::withMessages([
                'current_password' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }
    }

    private function ensureDeleteIsNotRateLimited(Request $request): void
    {
        if (RateLimiter::tooManyAttempts($this->deleteThrottleKey($request), 3)) {
            $seconds = RateLimiter::availableIn($this->deleteThrottleKey($request));

            throw ValidationException::withMessages([
                'password' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }
    }
}
