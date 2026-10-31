<?php

use App\Actions\Transactions\StoreTransaction;
use App\Enums\TransactionType;
use App\Jobs\PushOwnerDraw;
use App\Models\Account;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);

    $this->actingAs(User::factory()->create());
    $this->account = Account::factory()->create(['opening_balance' => 0]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function recordDraw(int $sen = 1_000_00): Transaction
{
    return app(StoreTransaction::class)->handle([
        'type' => TransactionType::OwnerDraw->value,
        'account_id' => test()->account->id,
        'amount' => $sen,
        'date' => '2026-10-15',
        'description' => 'Gaji sendiri Oktober',
    ]);
}

it('queues a push when an owner draw is recorded', function () {
    Bus::fake();

    $draw = recordDraw();

    Bus::assertDispatched(
        PushOwnerDraw::class,
        fn (PushOwnerDraw $job) => $job->transaction->is($draw),
    );
});

it('queues nothing for any other kind of transaction', function () {
    Bus::fake();

    app(StoreTransaction::class)->handle([
        'type' => TransactionType::Income->value,
        'account_id' => $this->account->id,
        'amount' => 3_000_00,
        'date' => '2026-10-15',
        'description' => 'Projek',
    ]);

    Bus::assertNotDispatched(PushOwnerDraw::class);
});

it('posts the draw to the configured endpoint', function () {
    Setting::put('owner_draw_webhook_url', 'https://tracker.test/hooks/kira');
    Http::fake(['tracker.test/*' => Http::response(['ok' => true])]);

    (new PushOwnerDraw(recordDraw(1_250_50)))->handle();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://tracker.test/hooks/kira'
            && $request->method() === 'POST'
            && $body['event'] === 'owner_draw.recorded'
            // Sen, like everything else that crosses a wire here.
            && $body['amount'] === 125_050
            && $body['currency'] === 'MYR'
            && $body['date'] === '2026-10-15'
            && $body['description'] === 'Gaji sendiri Oktober';
    });
});

it('signs the payload when a secret is set', function () {
    Setting::putMany([
        'owner_draw_webhook_url' => 'https://tracker.test/hooks/kira',
        'owner_draw_webhook_secret' => 'rahsia',
    ]);
    Http::fake();

    (new PushOwnerDraw(recordDraw()))->handle();

    Http::assertSent(function ($request) {
        $expected = hash_hmac('sha256', (string) json_encode($request->data()), 'rahsia');

        // The receiver can check this against its own copy of the secret.
        return $request->hasHeader('X-KiraBisnes-Signature', $expected);
    });
});

it('sends no signature when no secret is set', function () {
    Setting::put('owner_draw_webhook_url', 'https://tracker.test/hooks/kira');
    Http::fake();

    (new PushOwnerDraw(recordDraw()))->handle();

    Http::assertSent(fn ($request) => ! $request->hasHeader('X-KiraBisnes-Signature'));
});

it('does nothing at all when no endpoint is configured', function () {
    Http::fake();

    (new PushOwnerDraw(recordDraw()))->handle();

    Http::assertNothingSent();
});

it('logs a rejection rather than raising it', function () {
    // A tracker being down must not stop the owner recording that they paid
    // themselves.
    Setting::put('owner_draw_webhook_url', 'https://tracker.test/hooks/kira');
    Http::fake(['tracker.test/*' => Http::response('nope', 500)]);
    Log::spy();

    (new PushOwnerDraw(recordDraw()))->handle();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => $message === 'Owner draw push rejected');
});

it('saves the endpoint from the business settings page', function () {
    $this->post(route('business.update'), [
        'business_name' => 'KiraBisnes',
        'invoice_prefix' => 'INV',
        'quotation_prefix' => 'QUO',
        'default_payment_terms_days' => 14,
        'default_document_locale' => 'ms',
        'owner_draw_webhook_url' => 'https://tracker.test/hooks/kira',
        'owner_draw_webhook_secret' => 'rahsia',
    ])->assertRedirect();

    expect(Setting::get('owner_draw_webhook_url'))->toBe('https://tracker.test/hooks/kira')
        ->and(Setting::get('owner_draw_webhook_secret'))->toBe('rahsia');
});

it('refuses an endpoint that is not https', function () {
    // The payload says what the owner pays themselves.
    $this->from(route('business.edit'))
        ->post(route('business.update'), [
            'business_name' => 'KiraBisnes',
            'invoice_prefix' => 'INV',
            'quotation_prefix' => 'QUO',
            'default_payment_terms_days' => 14,
            'default_document_locale' => 'ms',
            'owner_draw_webhook_url' => 'http://tracker.test/hooks/kira',
        ])
        ->assertSessionHasErrors('owner_draw_webhook_url');
});
