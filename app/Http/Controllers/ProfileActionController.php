<?php

namespace App\Http\Controllers;

use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Support\CourseCatalog;
use App\Support\LocationAddress;
use App\Services\AccountService;
use App\Services\AcademicSettingsService;
use App\Services\GoogleDriveService;
use App\Services\ScholarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileActionController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private AcademicSettingsService $academic,
        private AccountService $accounts,
        private GoogleDriveService $drive,
    ) {}

    /**
     * Update non-credential profile fields only.
     * Email, scholar ID, and password cannot be changed here.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $currentProvince = $user->provinceName();
        $provinceProvided = $request->exists('province');
        $rawProvince = $provinceProvided ? trim((string) $request->input('province')) : '';
        $provinceToSave = $currentProvince;

        if ($provinceProvided) {
            if ($rawProvince === '') {
                $provinceToSave = null;
            } else {
                $canonical = ScholarshipProgram::canonicalPickerProvince($rawProvince);
                if ($canonical !== null) {
                    $provinceToSave = $canonical;
                } elseif ($currentProvince !== null && strcasecmp($currentProvince, $rawProvince) === 0) {
                    $provinceToSave = $currentProvince;
                } else {
                    throw ValidationException::withMessages([
                        'province' => 'Please choose Surigao del Norte or Surigao del Sur.',
                    ])->redirectTo(route('user.profile'));
                }
            }
        }

        $keepCurrentCity = filled($provinceToSave)
            && filled($currentProvince)
            && strcasecmp((string) $provinceToSave, (string) $currentProvince) === 0;
        $allowedCities = ScholarshipProgram::municipalityOptions(
            $provinceToSave,
            $keepCurrentCity ? $user->municipalityName() : null
        );

        $clubId = $user->scholarship_club_id;
        $hasClubSchools = $clubId && ScholarshipClubSchool::query()
            ->where('scholarship_club_id', $clubId)
            ->exists();

        $data = $request->validate([
            'cellphone_number' => [
                'nullable',
                'string',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '') {
                        return;
                    }

                    $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
                    if (strlen($digits) < 10 || strlen($digits) > 15) {
                        $fail('Enter a valid cellphone number with 10 to 15 digits.');
                    }
                },
            ],
            'date_of_birth' => ['nullable', 'date', 'after:1900-01-01', 'before:today'],
            'street' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255', Rule::in(ScholarshipProgram::provinceOptions($currentProvince))],
            'city' => ['nullable', 'string', 'max:255', Rule::in($allowedCities)],
            'course_year_level' => CourseCatalog::courseRules(),
            'year_level' => ['nullable', 'string', 'max:50', Rule::in(CourseCatalog::yearLevels())],
            'scholarship_club_school_id' => $hasClubSchools
                ? [
                    'required',
                    'integer',
                    Rule::exists('scholarship_club_schools', 'id')->where(
                        fn ($query) => $query->where('scholarship_club_id', $clubId)
                    ),
                ]
                : ['nullable'],
        ], [
            'province.in' => 'Please choose Surigao del Norte or Surigao del Sur.',
            'city.in' => 'Please choose a Municipality or City that belongs to your Province.',
            'date_of_birth.before' => 'Date of Birth must be a past date.',
            'date_of_birth.after' => 'Please enter a valid Date of Birth.',
            'date_of_birth.date' => 'Please enter a valid Date of Birth.',
            'scholarship_club_school_id.required' => 'Please select your School/University.',
            'scholarship_club_school_id.exists' => 'Please select a School/University from the list added by Scholar Staff.',
            'year_level.in' => 'Please select a year level.',
        ]);

        $updates = collect($data)->only([
            'date_of_birth',
        ])->all();

        if ($request->exists('street')) {
            $updates['street'] = LocationAddress::normalizeStreet($data['street'] ?? null);
        }

        if ($provinceProvided) {
            $updates['province'] = $provinceToSave;
        }

        if ($request->exists('city')) {
            $city = trim((string) ($data['city'] ?? ''));
            $updates['city'] = $city !== '' ? $city : null;
        }

        $contactNumber = trim((string) ($data['cellphone_number'] ?? ''));
        $updates['cellphone_number'] = $contactNumber !== '' ? $contactNumber : null;

        if (array_key_exists('course_year_level', $data)) {
            $updates['course_year_level'] = CourseCatalog::normalize($data['course_year_level']);
        }

        if (array_key_exists('year_level', $data)) {
            $updates['year_level'] = $data['year_level'] ?: null;
        }

        if (! empty($data['scholarship_club_school_id']) && $clubId) {
            $school = ScholarshipClubSchool::query()
                ->where('scholarship_club_id', $clubId)
                ->findOrFail((int) $data['scholarship_club_school_id']);

            $updates['scholarship_club_school_id'] = $school->id;
            $updates['school_university'] = $school->name;
        }

        $user->update($updates);

        if ($user->isScholar()) {
            try {
                $this->drive->syncStudentFolderName($user->fresh());
            } catch (\Throwable $e) {
                Log::warning('Could not rename the scholar Drive folder after a name change.', [
                    'scholar_id' => $user->scholar_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->scholar->logActivity($user, 'profile', 'Profile information updated');

        return redirect()
            ->route('user.profile', ['updated' => 1])
            ->with('success', 'Profile updated successfully.');
    }

    public function updateAvatar(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->isScholar(), 403);

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'avatar.required' => 'Please choose a profile photo.',
            'avatar.image' => 'The profile photo must be an image.',
            'avatar.mimes' => 'The profile photo must be a JPG, JPEG, or PNG file.',
            'avatar.max' => 'The profile photo must not be larger than 2MB.',
        ]);

        $this->accounts->storeAvatar($user, $request->file('avatar'));
        $this->scholar->logActivity($user, 'profile', 'Profile photo updated');

        return back()->with('success', 'Profile photo updated.');
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

        if (! $user->isScholar()) {
            abort(403, 'Only scholars can set a Start Academic Year on this page.');
        }

        $validated = $request->validate([
            'program_start_year' => ['required', 'integer'],
        ]);

        $start = $this->scholar->setProgramStartYear($user, (int) $validated['program_start_year']);
        $this->scholar->logActivity($user, 'profile', 'Start Academic Year updated', [
            'program_start_year' => $start,
        ]);

        return redirect()
            ->route('user.profile', ['tab' => 'academic-settings'])
            ->with('success', 'Start Academic Year saved as AY '.$start.'–'.($start + 1).'. Year 1–Year 4 now follow from this year.');
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

        return redirect()
            ->route('user.profile', ['tab' => 'academic-settings'])
            ->with('success', "System academic period set to {$setting->semester} {$setting->year_start}-{$setting->year_end}. New attendance records will use this period.");
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
            ])->redirectTo(route('user.profile', ['tab' => 'security']));
        }

        $user->updatePassword($validated['password']);

        RateLimiter::clear($this->passwordThrottleKey($request));
        $request->session()->regenerate();

        $this->scholar->logActivity($user, 'security', 'Password changed');
        $this->scholar->notify($user, 'Password Changed', 'Your account password was updated successfully.', 'system', true);

        return redirect()
            ->route('user.profile', ['tab' => 'security'])
            ->with('success', 'Password changed successfully.');
    }

    public function destroy(Request $request)
    {
        $this->ensureDeleteIsNotRateLimited($request);

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        if ($user->isPermanentAdmin()) {
            throw ValidationException::withMessages([
                'password' => 'The designated administrator account is permanent and cannot be deleted.',
            ]);
        }

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
