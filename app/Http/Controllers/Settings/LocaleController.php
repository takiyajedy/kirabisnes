<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LocaleUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class LocaleController extends Controller
{
    /**
     * Store the UI language on the user and in a cookie, so guest pages
     * (login, password reset) stay in the same language.
     */
    public function update(LocaleUpdateRequest $request): RedirectResponse
    {
        $locale = $request->string('locale')->value();

        $user = $request->user();

        if ($user !== null) {
            $user->update(['locale' => $locale]);
        }

        // Translate the flash message in the language the user just picked.
        app()->setLocale($locale);

        Cookie::queue(cookie(
            name: 'locale',
            value: $locale,
            minutes: 60 * 24 * 365,
            httpOnly: false,
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('locale.saved'),
        ]);

        return back();
    }
}
