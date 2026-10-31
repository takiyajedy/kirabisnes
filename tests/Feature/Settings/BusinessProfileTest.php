<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('shows the business profile page with the stored settings', function () {
    Setting::put('business_name', 'Azdi Digital Studio');

    $this->actingAs($this->user)
        ->get(route('business.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Business')
            ->where('settings.business_name', 'Azdi Digital Studio')
            ->has('documentLocales', 2),
        );
});

it('stores the business profile', function () {
    $this->actingAs($this->user)
        ->post(route('business.update'), [
            'business_name' => 'Azdi Digital Studio',
            'business_reg_no' => '202501234567',
            'business_email' => 'hello@azdi.test',
            'business_phone' => '+60123456789',
            'business_address' => 'Lot 1, Jalan Satu, 50000 Kuala Lumpur',
            'bank_details' => 'Maybank 1234567890',
            'invoice_footer_note' => ['ms' => 'Terima kasih.', 'en' => 'Thank you.'],
            'invoice_prefix' => 'INV',
            'quotation_prefix' => 'QUO',
            'default_payment_terms_days' => 30,
            'default_document_locale' => 'en',
        ])
        ->assertRedirect(route('business.edit'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Profil bisnes dikemas kini.');

    expect(Setting::get('business_name'))->toBe('Azdi Digital Studio')
        ->and(Setting::get('default_payment_terms_days'))->toBe(30)
        ->and(Setting::get('default_document_locale'))->toBe('en')
        ->and(Setting::get('invoice_footer_note'))
        ->toBe(['ms' => 'Terima kasih.', 'en' => 'Thank you.']);
});

it('uppercases the document prefixes', function () {
    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload(['invoice_prefix' => 'inv']))
        ->assertRedirect(route('business.edit'));

    expect(Setting::get('invoice_prefix'))->toBe('INV');
});

it('requires a business name', function () {
    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload(['business_name' => '']))
        ->assertSessionHasErrors('business_name');
});

it('rejects a prefix with punctuation', function () {
    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload(['invoice_prefix' => 'IN/V']))
        ->assertSessionHasErrors('invoice_prefix');
});

it('rejects payment terms outside a year', function () {
    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload(['default_payment_terms_days' => 400]))
        ->assertSessionHasErrors('default_payment_terms_days');
});

it('stores an uploaded logo and exposes its url', function () {
    Storage::fake('public');

    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload([
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]))
        ->assertRedirect(route('business.edit'));

    $path = Setting::get('logo_path');

    expect($path)->toBeString();
    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->user)
        ->get(route('business.edit'))
        ->assertInertia(fn ($page) => $page->whereNot('logoUrl', null));
});

it('replaces the previous logo instead of leaving it behind', function () {
    Storage::fake('public');

    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload([
            'logo' => UploadedFile::fake()->image('first.png'),
        ]));

    $first = Setting::get('logo_path');

    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload([
            'logo' => UploadedFile::fake()->image('second.png'),
        ]));

    $second = Setting::get('logo_path');

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('removes the logo on request', function () {
    Storage::fake('public');

    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload([
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]));

    $path = Setting::get('logo_path');

    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload(['remove_logo' => true]))
        ->assertRedirect(route('business.edit'));

    expect(Setting::get('logo_path'))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects a logo that is not an image', function () {
    Storage::fake('public');

    $this->actingAs($this->user)
        ->post(route('business.update'), businessPayload([
            'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('logo');
});

it('keeps guests out', function () {
    $this->get(route('business.edit'))->assertRedirect(route('login'));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function businessPayload(array $overrides = []): array
{
    return [
        'business_name' => 'Azdi Digital Studio',
        'invoice_prefix' => 'INV',
        'quotation_prefix' => 'QUO',
        'default_payment_terms_days' => 14,
        'default_document_locale' => 'ms',
        ...$overrides,
    ];
}
