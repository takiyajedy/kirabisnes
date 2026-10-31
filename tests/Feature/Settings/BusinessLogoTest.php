<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The mark the app shows for the business: the uploaded logo when there is
 * one, the built-in icon when there is not.
 */
beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->actingAs(User::factory()->create());
});

it('shares nothing when no logo has been uploaded', function () {
    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('logo', null));
});

it('shares the uploaded logo with every page', function () {
    Storage::fake('public');

    $this->post(route('business.update'), businessPayload([
        'logo' => UploadedFile::fake()->image('logo.png', 240, 80),
    ]))->assertRedirect();

    $path = Setting::get('logo_path');

    expect($path)->not->toBeNull();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('logo', Storage::disk('public')->url($path)),
        );
});

it('shows the logo on the sign-in page too', function () {
    Storage::fake('public');

    $this->post(route('business.update'), businessPayload([
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]))->assertRedirect();

    auth()->logout();

    // A guest sees the same mark: it is the same business.
    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page->where('logo', fn ($url) => $url !== null));
});

it('goes back to the built-in mark when the logo is removed', function () {
    Storage::fake('public');

    $this->post(route('business.update'), businessPayload([
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]));

    $this->post(route('business.update'), businessPayload([
        'remove_logo' => true,
    ]))->assertRedirect();

    expect(Setting::get('logo_path'))->toBeNull();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('logo', null));
});

it('ignores a logo path that was cleared to an empty string', function () {
    Setting::put('logo_path', '');

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('logo', null));
});
