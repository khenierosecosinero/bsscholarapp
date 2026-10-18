<?php

namespace App\Providers;

use App\Console\Commands\ServeCommand;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Services\MailConfigService;
use App\Support\NetworkAwareVite;
use Illuminate\Foundation\Console\ServeCommand as FrameworkServeCommand;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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

        // Recover from stale config:cache that baked blank MAIL_USERNAME/PASSWORD.
        $this->app->make(MailConfigService::class)->syncRuntimeCredentials();

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

        View::composer([
            'layouts.admin',
            'layouts.staff',
            'layouts.user',
            'admin.*',
            'partials.admin-*',
        ], function ($view) {
            if (! Schema::hasTable('academic_settings')) {
                return;
            }

            $academic = app(AcademicSettingsService::class);
            $view->with('academicPeriod', $academic->selectedPeriod());
            $view->with('academicYearOptions', $academic->yearOptions());
            $view->with('semesterOptions', AcademicSettingsService::SEMESTERS);
            $view->with('adminAcademicYearFilter', $academic->adminYearFilter());
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
