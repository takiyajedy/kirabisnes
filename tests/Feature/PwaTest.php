<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Storage;

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

/*
 * The home screen icon follows the business logo, the same way the sidebar
 * and the sign-in pages do.
 */

/** A solid PNG of the given shape, standing in for an uploaded logo. */
function logoPng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, 5, 150, 105));

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    return $png;
}

/** Put a logo on the public disk and point the settings at it. */
function uploadLogo(string $png = ''): string
{
    Storage::fake('public');

    $path = 'business/logo.png';

    Storage::disk('public')->put($path, $png === '' ? logoPng(400, 400) : $png);
    Setting::put('logo_path', $path);

    return $path;
}

/** Where the generated icons live, without the cache-busting stamp. */
function iconPath(string $src): string
{
    return 'business/icons/'.basename((string) parse_url($src, PHP_URL_PATH));
}

it('draws the home screen icons from the uploaded logo', function () {
    uploadLogo();

    $icons = $this->get(route('manifest'))->assertOk()->json('icons');

    expect($icons)->toHaveCount(3);

    foreach ($icons as $icon) {
        expect($icon['src'])->toContain('business/icons/')
            ->and($icon['type'])->toBe('image/png');

        $path = iconPath($icon['src']);

        expect(Storage::disk('public')->exists($path))->toBeTrue();

        // A browser measures the file against the size the manifest claims,
        // and drops the install prompt when they disagree.
        [$width, $height] = (array) getimagesizefromstring(
            (string) Storage::disk('public')->get($path),
        );

        expect("{$width}x{$height}")->toBe($icon['sizes']);
    }
});

it('keeps the built-in mark when no logo has been uploaded', function () {
    Setting::put('logo_path', null);

    $this->get(route('manifest'))
        ->assertJsonPath('icons.0.src', '/icons/icon-192.png')
        ->assertJsonPath('icons.2.src', '/icons/icon-512.png');
});

it('stamps the icon URLs so a browser fetches the new ones', function () {
    uploadLogo();

    $src = (string) $this->get(route('manifest'))->json('icons.0.src');

    expect($src)->toContain('?v='.Storage::disk('public')->lastModified('business/logo.png'));
});

it('fits a wide wordmark in the square instead of cropping it', function () {
    uploadLogo(logoPng(600, 200));

    $src = (string) $this->get(route('manifest'))->json('icons.1.src');
    $icon = (string) Storage::disk('public')->get(iconPath($src));

    $image = imagecreatefromstring($icon);

    expect($image)->not->toBeFalse();

    // The logo is three times as wide as it is tall, so in a 512 box it has
    // to leave the top and bottom empty. The centre row is the logo and the
    // top edge is not.
    $centre = imagecolorat($image, 256, 256);
    $top = imagecolorat($image, 256, 4);

    expect($centre)->not->toBe($top)
        // White, not transparent: iOS paints a transparent icon onto black.
        ->and($top)->toBe(imagecolorat($image, 4, 4));

    imagedestroy($image);
});

it('insets the maskable icon far enough for Android to crop it', function () {
    uploadLogo();

    $src = (string) $this->get(route('manifest'))->json('icons.2.src');
    $image = imagecreatefromstring((string) Storage::disk('public')->get(iconPath($src)));

    // Android keeps a circle 80% of the icon wide. The corners of the
    // central square it leaves have to still be background, or the logo
    // loses its edges.
    $inset = (int) round(512 * 0.1);

    expect(imagecolorat($image, $inset, $inset))->toBe(imagecolorat($image, 2, 2))
        ->and(imagecolorat($image, 256, 256))->not->toBe(imagecolorat($image, 2, 2));

    imagedestroy($image);
});

it('puts the logo on the iOS home screen too', function () {
    uploadLogo();
    $this->actingAs(User::factory()->create());

    $html = (string) $this->get(route('dashboard'))->getContent();

    expect($html)->toContain('rel="apple-touch-icon" href="/storage/business/icons/icon-192.png');
});

it('falls back to the built-in touch icon without a logo', function () {
    Setting::put('logo_path', null);
    $this->actingAs(User::factory()->create());

    expect((string) $this->get(route('dashboard'))->getContent())
        ->toContain('rel="apple-touch-icon" href="/apple-touch-icon.png"');
});

it('removing the logo brings the built-in icons back', function () {
    uploadLogo();
    $this->get(route('manifest'))->assertJsonPath('icons.0.src', fn ($src) => str_contains((string) $src, 'business/icons/'));

    Setting::put('logo_path', null);

    $this->get(route('manifest'))->assertJsonPath('icons.0.src', '/icons/icon-192.png');
    expect(Storage::disk('public')->exists('business/icons/icon-192.png'))->toBeTrue();
});

it('falls back to the built-in mark when the logo is not a readable image', function () {
    uploadLogo('this is not a png');

    // Nothing blows up and the manifest still installs.
    $this->get(route('manifest'))
        ->assertOk()
        ->assertJsonPath('icons.0.src', '/icons/icon-192.png');
});

it('falls back when the settings point at a logo that is gone', function () {
    uploadLogo();
    Storage::disk('public')->delete('business/logo.png');

    $this->get(route('manifest'))->assertJsonPath('icons.0.src', '/icons/icon-192.png');
});
