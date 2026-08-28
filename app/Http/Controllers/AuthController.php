<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ScholarshipProgram;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureLoginIsNotRateLimited($request);

        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials, true)) {
            RateLimiter::hit($this->loginThrottleKey($request), 60);

            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->loginThrottleKey($request));

        $user = Auth::user();

        if (! $user->canLogin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account has been rejected and can no longer access the system. Please contact Scholar Staff for assistance.',
            ]);
        }

        $user->markLogin();
        $request->session()->regenerate();

        if ($user->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        if ($user->isScholarStaff()) {
            return redirect()->intended(route('staff.dashboard'));
        }

        if ($user->isPendingApproval()) {
            $request->session()->put('show_pending_approval_modal', true);
        }

        return redirect()->intended(route('user.dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register', [
            'programGroups' => ScholarshipProgram::groupedActiveForPicker(citiesOnly: true),
        ]);
    }

    public function showStaffRegister()
    {
        return view('auth.staff-register', [
            'programGroups' => ScholarshipProgram::groupedActiveForPicker(citiesOnly: false),
        ]);
    }

    public function register(Request $request)
    {
        $this->ensureRegisterIsNotRateLimited($request);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'scholar_id' => ['required', 'string', 'max:100', 'unique:users,scholar_id'],
            'scholarship_program_id' => [
                'required',
                Rule::exists('scholarship_programs', 'id')->where(function ($query) {
                    $query->where('is_active', true)
                        ->where('location_type', 'city_municipality');
                }),
            ],
            'school_university' => ['nullable', 'string', 'max:255'],
            'course_year_level' => ['nullable', 'string', 'max:255'],
            'cellphone_number' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'scholarship_program_id.required' => 'Please select your city scholar program.',
            'scholarship_program_id.exists' => 'Please select a valid city scholar program.',
        ]);

        DB::transaction(function () use ($data) {
            $user = User::register([
                'full_name' => $data['full_name'],
                'scholar_id' => $data['scholar_id'],
                'scholarship_program_id' => $data['scholarship_program_id'],
                'school_university' => $data['school_university'] ?? null,
                'course_year_level' => $data['course_year_level'] ?? null,
                'cellphone_number' => $data['cellphone_number'] ?? null,
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_SCHOLAR,
                'status' => 'pending',
            ]);

            $this->accounts->provisionNewAccount($user);
        });

        RateLimiter::hit($this->registerThrottleKey($request), 60);

        return redirect()->route('login')
            ->with('success', 'Scholar account created successfully. Your account is permanent — you may log in at any time, including after a long period of inactivity, while Scholar Staff reviews your registration.');
    }

    public function registerStaff(Request $request)
    {
        $this->ensureStaffRegisterIsNotRateLimited($request);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'scholar_id' => ['required', 'string', 'max:100', 'unique:users,scholar_id'],
            'scholarship_program_id' => [
                'required',
                Rule::exists('scholarship_programs', 'id')->where('is_active', true),
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'scholarship_program_id.required' => 'Please select the city or province scholar program you will manage.',
            'scholarship_program_id.exists' => 'Please select a valid city or province scholar program.',
        ]);

        DB::transaction(function () use ($data) {
            $user = User::register([
                'full_name' => $data['full_name'],
                'scholar_id' => $data['scholar_id'],
                'scholarship_program_id' => $data['scholarship_program_id'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_SCHOLAR_STAFF,
            ]);

            $this->accounts->provisionNewStaffAccount($user);
        });

        RateLimiter::hit($this->staffRegisterThrottleKey($request), 60);

        return redirect()->route('login')
            ->with('success', 'Scholar staff account created successfully. Your account is permanent — you may log in at any time to manage scholars in your assigned location.');
    }

    public function dashboard()
    {
        return redirect()->route('user.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function loginThrottleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());
    }

    private function registerThrottleKey(Request $request): string
    {
        return 'register|'.$request->ip();
    }

    private function staffRegisterThrottleKey(Request $request): string
    {
        return 'staff-register|'.$request->ip();
    }

    private function ensureLoginIsNotRateLimited(Request $request): void
    {
        if (RateLimiter::tooManyAttempts($this->loginThrottleKey($request), 5)) {
            $seconds = RateLimiter::availableIn($this->loginThrottleKey($request));

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }
    }

    private function ensureRegisterIsNotRateLimited(Request $request): void
    {
        if (RateLimiter::tooManyAttempts($this->registerThrottleKey($request), 3)) {
            $seconds = RateLimiter::availableIn($this->registerThrottleKey($request));

            throw ValidationException::withMessages([
                'email' => "Too many registration attempts. Please try again in {$seconds} seconds.",
            ]);
        }
    }

    private function ensureStaffRegisterIsNotRateLimited(Request $request): void
    {
        if (RateLimiter::tooManyAttempts($this->staffRegisterThrottleKey($request), 3)) {
            $seconds = RateLimiter::availableIn($this->staffRegisterThrottleKey($request));

            throw ValidationException::withMessages([
                'email' => "Too many registration attempts. Please try again in {$seconds} seconds.",
            ]);
        }
    }
}
