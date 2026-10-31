<?php

namespace App\Http\Controllers;

use App\Services\AppIconService;
use App\Support\BusinessProfile;
use Illuminate\Http\JsonResponse;

/**
 * The web app manifest, so the app can be installed on a phone
 * (PLAN.md phase 6).
 *
 * Served rather than kept as a static file because the name and the icon on
 * the home screen should be the business's own, and both live in settings.
 */
class ManifestController extends Controller
{
    public function __construct(private readonly AppIconService $icons) {}

    public function __invoke(): JsonResponse
    {
        $name = BusinessProfile::all()['business_name'] ?: config('app.name');

        return response()->json([
            'name' => $name,
            // What fits under the icon on a home screen.
            'short_name' => mb_substr((string) $name, 0, 12),
            'description' => __('pwa.description'),
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            // The manifest holds one colour; the two meta tags in the layout
            // are what give light and dark their own.
            'theme_color' => '#059669',
            'lang' => app()->getLocale(),
            'dir' => 'ltr',
            // The business's own logo when one has been uploaded, and the
            // built-in mark otherwise.
            'icons' => $this->icons->manifestIcons(),
        ], 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
