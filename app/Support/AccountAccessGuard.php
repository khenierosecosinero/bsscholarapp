<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountAccessGuard
{
    public static function redirectIfRestricted(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isAccessRestricted()) {
            return null;
        }

        $message = $user->accessRestrictedMessage();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('error', $message);
    }
}
