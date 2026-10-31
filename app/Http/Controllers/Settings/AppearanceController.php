<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppearanceUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Show the appearance (theme + language) settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Appearance');
    }

    /**
     * Store the colour scheme on the user and in a cookie. The cookie is what
     * lets the server render the right theme on first paint, with no flash.
     */
    public function update(AppearanceUpdateRequest $request): RedirectResponse
    {
        $appearance = $request->string('appearance')->value();

        $user = $request->user();

        if ($user !== null) {
            $user->update(['appearance' => $appearance]);
        }

        Cookie::queue(cookie(
            name: 'appearance',
            value: $appearance,
            minutes: 60 * 24 * 365,
            httpOnly: false,
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('appearance.saved'),
        ]);

        return back();
    }
}
