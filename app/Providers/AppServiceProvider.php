<?php

namespace App\Providers;

use App\Console\Commands\ServeCommand;
use App\Mail\GmailApiTransport;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Services\MailConfigService;
use App\Support\NetworkAwareVite;
use Illuminate\Foundation\Console\ServeCommand as FrameworkServeCommand;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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
        $this->app->singleton(
            Vite::class,
            NetworkAwareVite::class
        );

        $this->app->singleton(
            FrameworkServeCommand::class,
            ServeCommand::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Database Compatibility
        |--------------------------------------------------------------------------
        */

        // Prevent "Specified key was too long" on older MySQL versions.
        Schema::defaultStringLength(191);

        /*
        |--------------------------------------------------------------------------
        | Gmail API Mail Transport
        |--------------------------------------------------------------------------
        |
        | Register the custom Gmail API transport.
        |
        | This transport sends email through Gmail API over HTTPS
        | instead of connecting to Gmail SMTP.
        |
        | OAuth credentials are stored in config/services.php.
        |
        */

        Mail::extend('gmail_api', function (array $config = []) {

            return new GmailApiTransport(

                clientId: (string) config(
                    'services.gmail_api.client_id',
                    ''
                ),

                clientSecret: (string) config(
                    'services.gmail_api.client_secret',
                    ''
                ),

                refreshToken: (string) config(
                    'services.gmail_api.refresh_token',
                    ''
                ),

                senderAddress: (string) config(
                    'mail.from.address',
                    ''
                )

            );

        });

        /*
        |--------------------------------------------------------------------------
        | Mail Configuration
        |--------------------------------------------------------------------------
        */

        // Recover from stale config:cache that baked blank
        // MAIL_USERNAME or MAIL_PASSWORD values.
        $this->app
            ->make(MailConfigService::class)
            ->syncRuntimeCredentials();

        /*
        |--------------------------------------------------------------------------
        | Local URL Configuration
        |--------------------------------------------------------------------------
        */

        $this->useRequestHostForLocalUrls();

        /*
        |--------------------------------------------------------------------------
        | Remember Me Authentication
        |--------------------------------------------------------------------------
        */

        // Keep remember-me cookies valid for 10 years.
        // Accounts themselves never expire.
        $this->app->afterResolving('auth', function ($auth) {

            $guard = $auth->guard('web');

            if (method_exists($guard, 'setRememberDuration')) {
                $guard->setRememberDuration(5256000);
            }

        });

        /*
        |--------------------------------------------------------------------------
        | Staff Route Binding
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Scholar Route Binding
        |--------------------------------------------------------------------------
        */

        Route::bind('scholar', function (string $value) {

            return User::query()
                ->whereKey($value)
                ->where('role', User::ROLE_SCHOLAR)
                ->firstOrFail();

        });

        /*
        |--------------------------------------------------------------------------
        | Academic Settings View Composer
        |--------------------------------------------------------------------------
        */

        View::composer([

            'layouts.admin',
            'layouts.staff',
            'layouts.user',
            'admin.*',
            'partials.admin-*',

        ], function ($view) {

            $hasAcademicSettings = app()->bound('request')
                ? request()->attributes->get('academic_settings_table')
                : null;

            if ($hasAcademicSettings === null) {

                $hasAcademicSettings = Schema::hasTable(
                    'academic_settings'
                );

                if (app()->bound('request')) {

                    request()->attributes->set(
                        'academic_settings_table',
                        $hasAcademicSettings
                    );

                }
            }

            if (! $hasAcademicSettings) {
                return;
            }

            $academic = app(
                AcademicSettingsService::class
            );

            $view->with(
                'academicPeriod',
                $academic->selectedPeriod()
            );

            $view->with(
                'academicYearOptions',
                $academic->yearOptions()
            );

            $view->with(
                'semesterOptions',
                AcademicSettingsService::SEMESTERS
            );

            $view->with(
                'adminAcademicYearFilter',
                $academic->adminYearFilter()
            );

        });
    }

    /**
     * Follow the Host header on local HTTP requests
     * so LAN devices are not redirected to localhost / 0.0.0.0.
     */
    private function useRequestHostForLocalUrls(): void
    {
        if (! $this->app->environment(
            'local',
            'development',
            'testing'
        )) {
            return;
        }

        if (
            $this->app->runningInConsole()
            && ! $this->app->runningUnitTests()
        ) {
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