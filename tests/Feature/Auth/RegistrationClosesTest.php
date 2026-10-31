<?php

use App\Models\User;

it('allows registration while there is no owner account', function () {
    $this->get(route('register'))->assertOk();
});

it('closes registration once the owner account exists', function () {
    User::factory()->create();

    $this->get(route('register'))->assertNotFound();
    $this->post(route('register.store'), [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->count())->toBe(1);
});

it('hides the sign up link once registration is closed', function () {
    User::factory()->create();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canRegister', false));
});

it('offers the sign up link while registration is open', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canRegister', true));
});
