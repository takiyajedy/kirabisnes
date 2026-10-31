<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BusinessProfileUpdateRequest;
use App\Models\Setting;
use App\Services\AppIconService;
use App\Support\BusinessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessProfileController extends Controller
{
    public function __construct(private readonly AppIconService $icons) {}

    /**
     * Show the business profile settings page.
     */
    public function edit(): Response
    {
        $settings = BusinessProfile::all();

        return Inertia::render('settings/Business', [
            'settings' => $settings,
            'logoUrl' => $settings['logo_path'] === null
                ? null
                : Storage::disk('public')->url($settings['logo_path']),
            'documentLocales' => array_map(
                fn (Locale $locale) => [
                    'value' => $locale->value,
                    'label' => $locale->nativeLabel(),
                ],
                Locale::cases(),
            ),
        ]);
    }

    /**
     * Update the business profile settings.
     */
    public function update(BusinessProfileUpdateRequest $request): RedirectResponse
    {
        $values = $request->safe()->except(['logo', 'remove_logo']);

        if ($request->boolean('remove_logo')) {
            $this->deleteExistingLogo();

            $values['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            $this->deleteExistingLogo();

            $values['logo_path'] = $request->file('logo')
                ->store('business', 'public');
        }

        Setting::putMany($values);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('business.updated'),
        ]);

        return to_route('business.edit');
    }

    private function deleteExistingLogo(): void
    {
        $existing = Setting::get('logo_path');

        if (is_string($existing) && $existing !== '') {
            Storage::disk('public')->delete($existing);
        }

        // The home screen icons were drawn from that logo. A replacement is
        // drawn again on the next request that needs one.
        $this->icons->clear();
    }
}
