<?php

namespace App\Providers;

use App\Services\AppIconService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewInstance;

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

        // The tab icon and the iOS home screen icon are plain <link> tags,
        // so the layout needs their URLs; neither is read from the manifest.
        // Composed rather than shared so they are only worked out for the
        // page that prints them.
        View::composer('app', function (ViewInstance $view): void {
            $icons = $this->app->make(AppIconService::class);

            $view->with([
                'appleTouchIcon' => $icons->appleTouchIconUrl(),
                'businessFavicon' => $icons->faviconUrl(),
            ]);
        });
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
