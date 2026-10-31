<?php

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Support\Carbon;

it('falls back to the configured locale for guests with no hints', function () {
    // Symfony's test request always carries a default Accept-Language header,
    // so it has to be cleared to reach the last step of the chain.
    $this->withHeader('Accept-Language', '')
        ->get(route('login'))
        ->assertOk();

    expect(app()->getLocale())->toBe(config('app.locale'))
        ->and(config('app.locale'))->toBe('ms');
});

it('uses the Accept-Language header for guests', function () {
    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
        ->get(route('login'))
        ->assertOk();

    expect(app()->getLocale())->toBe('en');
});

it('ignores unsupported languages in the Accept-Language header', function () {
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get(route('login'))
        ->assertOk();

    expect(app()->getLocale())->toBe('ms');
});

it('prefers the locale cookie over the Accept-Language header', function () {
    $this->withUnencryptedCookie('locale', 'en')
        ->withHeader('Accept-Language', 'ms')
        ->get(route('login'))
        ->assertOk();

    expect(app()->getLocale())->toBe('en');
});

it('ignores an unsupported locale cookie', function () {
    $this->withUnencryptedCookie('locale', 'de')
        ->withHeader('Accept-Language', '')
        ->get(route('login'))
        ->assertOk();

    expect(app()->getLocale())->toBe('ms');
});

it('prefers the signed in user locale over the cookie and header', function () {
    $user = User::factory()->create(['locale' => Locale::English]);

    $this->actingAs($user)
        ->withUnencryptedCookie('locale', 'ms')
        ->withHeader('Accept-Language', 'ms')
        ->get(route('dashboard'))
        ->assertOk();

    expect(app()->getLocale())->toBe('en');
});

it('sets the Carbon locale alongside the application locale', function () {
    $user = User::factory()->create(['locale' => Locale::English]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect(Carbon::getLocale())->toBe('en');
});

it('shares the locale and the available locales with the front end', function () {
    $user = User::factory()->create(['locale' => Locale::Malay]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'ms')
            ->where('localeTag', 'ms-MY')
            ->has('availableLocales', 2)
            ->where('availableLocales.0.value', 'ms')
            ->where('availableLocales.0.short', 'BM')
            ->where('availableLocales.1.value', 'en')
            ->where('availableLocales.1.short', 'EN'),
        );
});

it('translates validation errors into the active language', function () {
    $this->withUnencryptedCookie('locale', 'ms')
        ->post(route('login.store'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'Medan e-mel wajib diisi.']);

    $this->withUnencryptedCookie('locale', 'en')
        ->post(route('login.store'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'The email field is required.']);
});
