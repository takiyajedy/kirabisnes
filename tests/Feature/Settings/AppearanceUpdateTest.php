<?php

use App\Enums\Appearance;
use App\Models\User;

it('shows the appearance settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/Appearance'));
});

it('stores the theme on the user and in a cookie', function () {
    $user = User::factory()->create(['appearance' => Appearance::System]);

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('appearance.update'), ['appearance' => 'dark'])
        ->assertRedirect(route('appearance.edit'))
        ->assertPlainCookie('appearance', 'dark')
        ->assertInertiaFlash('toast.type', 'success');

    expect($user->refresh()->appearance)->toBe(Appearance::Dark);
});

it('rejects an unknown theme', function () {
    $user = User::factory()->create(['appearance' => Appearance::System]);

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('appearance.update'), ['appearance' => 'sepia'])
        ->assertSessionHasErrors('appearance');

    expect($user->refresh()->appearance)->toBe(Appearance::System);
});

it('renders the saved theme on first paint, so there is no flash', function () {
    $user = User::factory()->create(['appearance' => Appearance::Dark]);

    // A full page load (not an Inertia visit) carries the root template.
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('class="dark"', escape: false);
});

it('prefers the user theme over the cookie, so devices stay in sync', function () {
    $user = User::factory()->create(['appearance' => Appearance::Dark]);

    $this->actingAs($user)
        ->withUnencryptedCookie('appearance', 'light')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('appearance', 'dark'));
});

it('falls back to the cookie for guests', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('appearance', 'dark'));
});
