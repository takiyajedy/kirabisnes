<?php

namespace App\Http\Middleware;

use App\Actions\Time\StartTimer;
use App\Enums\Locale;
use App\Http\Resources\TimeEntryResource;
use App\Models\Setting;
use App\Support\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * The uploaded logo, or null when there is none.
     */
    private function logoUrl(): ?string
    {
        $path = Setting::get('logo_path');

        return is_string($path) && $path !== ''
            ? Storage::disk('public')->url($path)
            : null;
    }

    /**
     * The one timer that can be running, for the top bar widget.
     *
     * @return array<string, mixed>|null
     */
    private function runningTimer(Request $request): ?array
    {
        if ($request->user() === null) {
            return null;
        }

        $entry = StartTimer::current();

        return $entry === null
            ? null
            : TimeEntryResource::make($entry)->resolve();
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => Setting::get('business_name') ?: config('app.name'),
            // The business's own logo, shown wherever the app marks itself:
            // the sidebar and the sign-in pages. Null falls back to the
            // built-in mark. Settings are cached, so this costs nothing
            // after the first read.
            'logo' => $this->logoUrl(),
            'auth' => [
                'user' => $request->user(),
                // Only the count: the bell sits on every page, and the list
                // itself is fetched when it is actually opened.
                'unreadNotifications' => $request->user()?->unreadNotifications()->count() ?? 0,
            ],
            // The top bar shows a running timer wherever the owner is, so
            // the one open entry — at most one by design — is shared.
            'runningTimer' => $this->runningTimer($request),
            'locale' => app()->getLocale(),
            'localeTag' => Locale::from(app()->getLocale())->tag(),
            'availableLocales' => array_map(
                fn (Locale $locale) => [
                    'value' => $locale->value,
                    'label' => $locale->nativeLabel(),
                    'short' => $locale->shortLabel(),
                ],
                Locale::cases(),
            ),
            'appearance' => View::shared('appearance', 'system'),
            'canRegister' => Registration::isOpen(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
