<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Abuse-resistant limits for public and owner write workflows.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for(
            'business-enquiry',
            function (Request $request): Limit {
                $key = implode('|', [
                    'business-enquiry',
                    (string) ($request->ip() ?? 'unknown'),
                    (string) $request->route('slug'),
                ]);

                return Limit::perMinute(3)
                    ->by(hash('sha256', $key));
            }
        );

        RateLimiter::for(
            'business-review',
            function (Request $request): Limit {
                $key = implode('|', [
                    'business-review',
                    (string) ($request->user()?->id ?? 'guest'),
                    (string) $request->route('slug'),
                ]);

                return Limit::perMinute(3)
                    ->by(hash('sha256', $key));
            }
        );

        RateLimiter::for(
            'publication-request',
            function (Request $request): Limit {
                $key = implode('|', [
                    'publication-request',
                    (string) ($request->user()?->id ?? 'guest'),
                    (string) $request->route('business'),
                ]);

                return Limit::perMinute(2)
                    ->by(hash('sha256', $key));
            }
        );
    }
    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
