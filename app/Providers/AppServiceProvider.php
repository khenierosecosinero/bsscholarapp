<?php

namespace App\Providers;

use App\Console\Commands\ServeCommand;
use App\Models\User;
use App\Support\NetworkAwareVite;
use Illuminate\Foundation\Console\ServeCommand as FrameworkServeCommand;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Vite::class, NetworkAwareVite::class);
        $this->app->singleton(FrameworkServeCommand::class, ServeCommand::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent "Specified key was too long" on older MySQL versions
        Schema::defaultStringLength(191);

        $this->useRequestHostForLocalUrls();

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

    /**
     * Follow the Host header on local HTTP requests so LAN devices
     * are not redirected to localhost / 0.0.0.0.
     */
    private function useRequestHostForLocalUrls(): void
    {
        if (! $this->app->environment('local', 'development', 'testing')) {
            return;
        }

        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return;
        }

        if (! $this->app->bound('request')) {
            return;
        }

        URL::forceRootUrl(null);

        $scheme = request()->getScheme();

        if (in_array($scheme, ['http', 'https'], true)) {
            URL::forceScheme($scheme);
        }
    }
}
