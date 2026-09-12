<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent "Specified key was too long" on older MySQL versions
        Schema::defaultStringLength(191);

        // Keep remember-me cookies valid for 10 years so accounts stay signed in
        // after long inactivity. Accounts themselves never expire.
        $this->app->afterResolving('auth', function ($auth) {
            $guard = $auth->guard('web');
            if (method_exists($guard, 'setRememberDuration')) {
                $guard->setRememberDuration(5256000);
            }
        });

        Route::bind('staffMember', function (string $value) {
            $staffMember = User::query()
                ->whereKey($value)
                ->where('role', User::ROLE_SCHOLAR_STAFF)
                ->firstOrFail();

            if (Auth::user()?->isAdmin()) {
                return $staffMember;
            }

            abort(404);
        });

        Route::bind('scholar', function (string $value) {
            return User::query()
                ->whereKey($value)
                ->where('role', User::ROLE_SCHOLAR)
                ->firstOrFail();
        });
    }
}
