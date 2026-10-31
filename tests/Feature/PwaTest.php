<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    $this->seed(SettingSeeder::class);
});

it('serves a manifest a browser can install from', function () {
    $this->get(route('manifest'))
        ->assertOk()
        ->assertHeader('content-type', 'application/manifest+json')
        ->assertJsonPath('start_url', '/dashboard')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonCount(3, 'icons')
        ->assertJsonPath('icons.0.sizes', '192x192')
        ->assertJsonPath('icons.1.sizes', '512x512')
        // Android crops the maskable one to its own shape.
        ->assertJsonPath('icons.2.purpose', 'maskable');
});

it('puts the business name on the home screen', function () {
    Setting::put('business_name', 'Tani Maju Enterprise');

    $this->get(route('manifest'))
        ->assertJsonPath('name', 'Tani Maju Enterprise')
        // What fits under an icon.
        ->assertJsonPath('short_name', 'Tani Maju En');
});

it('falls back to the app name when no business name is set', function () {
    Setting::put('business_name', '');

    $this->get(route('manifest'))
        ->assertJsonPath('name', config('app.name'));
});

it('describes itself in the reader language', function () {
    $this->actingAs(User::factory()->create(['locale' => 'en']));

    $this->get(route('manifest'))
        ->assertJsonPath('lang', 'en')
        ->assertJsonPath('description', 'Bookkeeping for a one-person business');
});

it('serves the icons it points at', function () {
    foreach ([192, 512] as $expected) {
        $path = public_path("/icons/icon-{$expected}.png");

        expect(file_exists($path))->toBeTrue();

        $size = getimagesize($path);

        // The manifest promises these dimensions; a browser checks them.
        expect($size)->not->toBeFalse()
            ->and($size[0])->toBe($expected)
            ->and($size[1])->toBe($expected);
    }
});

it('ships a service worker', function () {
    $worker = public_path('sw.js');

    expect(file_exists($worker))->toBeTrue();

    $contents = (string) file_get_contents($worker);

    // It has a fetch handler, which is what a browser looks for, and it
    // caches nothing on purpose.
    expect($contents)->toContain("addEventListener('fetch'")
        ->not->toContain('caches.open');
});

it('offers the manifest and both theme colours on the page', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->get(route('dashboard'))->getContent();

    expect($html)->toContain('rel="manifest"')
        // One colour per mode, which the manifest alone cannot do.
        ->toContain('content="#ffffff" media="(prefers-color-scheme: light)"')
        ->toContain('content="#09090b" media="(prefers-color-scheme: dark)"')
        ->toContain("navigator.serviceWorker.register('/sw.js')");
});

it('serves the manifest to a visitor who is not signed in', function () {
    // A browser fetches it before anyone logs in.
    $this->get(route('manifest'))->assertOk();
});
