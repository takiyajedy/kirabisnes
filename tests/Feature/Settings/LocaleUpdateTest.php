<?php

use App\Enums\Locale;
use App\Models\User;

it('stores the language on the user and in a cookie', function () {
    $user = User::factory()->create(['locale' => Locale::Malay]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('dashboard'))
        ->assertPlainCookie('locale', 'en');

    expect($user->refresh()->locale)->toBe(Locale::English);
});

it('flashes the confirmation in the newly chosen language', function () {
    $user = User::factory()->create(['locale' => Locale::Malay]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('locale.update'), ['locale' => 'en'])
        ->assertInertiaFlash('toast.message', 'Language updated.');

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('locale.update'), ['locale' => 'ms'])
        ->assertInertiaFlash('toast.message', 'Bahasa dikemas kini.');
});

it('lets guests switch language with only a cookie', function () {
    $this->from(route('login'))
        ->patch(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('login'))
        ->assertPlainCookie('locale', 'en');
});

it('rejects an unsupported language', function () {
    $user = User::factory()->create(['locale' => Locale::Malay]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('locale.update'), ['locale' => 'de'])
        ->assertSessionHasErrors('locale');

    expect($user->refresh()->locale)->toBe(Locale::Malay);
});
