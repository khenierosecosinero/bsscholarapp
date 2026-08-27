<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

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
    }
}
