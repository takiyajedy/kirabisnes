<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Support\BusinessProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells a personal money tracker about an owner draw (PLAN.md phase 6).
 *
 * The business paying the owner is income on the owner's personal books, so
 * the same event has to be entered twice unless something carries it across.
 * The endpoint is whatever the owner configures in settings; this sends a
 * small, stable payload and does not care what is on the other end.
 *
 * Queued, and failure is logged rather than raised: a tracker being down
 * must not stop the owner recording that they paid themselves.
 */
class PushOwnerDraw implements ShouldQueue
{
    use Queueable;

    /** Give up after three goes over about a minute. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(public Transaction $transaction) {}

    public function handle(): void
    {
        $settings = BusinessProfile::all();
        $url = $settings['owner_draw_webhook_url'];

        if (! is_string($url) || $url === '') {
            return;
        }

        $payload = [
            'event' => 'owner_draw.recorded',
            'id' => $this->transaction->id,
            // Sen, like everything else that crosses a wire in this app.
            'amount' => $this->transaction->amount,
            'currency' => 'MYR',
            'date' => $this->transaction->date->toDateString(),
            'description' => $this->transaction->description,
            'source' => config('app.name'),
        ];

        $request = Http::asJson()->timeout(10);

        $secret = $settings['owner_draw_webhook_secret'];

        if (is_string($secret) && $secret !== '') {
            // The receiver can check this against its own copy and know the
            // call really came from here.
            $request = $request->withHeader(
                'X-KiraBisnes-Signature',
                hash_hmac('sha256', (string) json_encode($payload), $secret),
            );
        }

        $response = $request->post($url, $payload);

        if ($response->failed()) {
            Log::warning('Owner draw push rejected', [
                'transaction' => $this->transaction->id,
                'status' => $response->status(),
            ]);
        }
    }

    /**
     * The tracker being unreachable is not the owner's problem to see.
     */
    public function failed(\Throwable $exception): void
    {
        Log::warning('Owner draw push failed', [
            'transaction' => $this->transaction->id,
            'reason' => $exception->getMessage(),
        ]);
    }
}
