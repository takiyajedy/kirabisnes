<?php

use App\Enums\Locale;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\RecurringItem;
use App\Models\User;
use App\Notifications\RecurringCostDue;
use App\Notifications\RecurringInvoiceDrafted;
use App\Support\DocumentDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 01:15:00');

    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('stores a translation key, not a finished sentence', function () {
    // A notification outlives the language it was written in, so the text is
    // built when it is read (PLAN.md phase 4).
    $owner = User::factory()->create(['locale' => Locale::Malay]);
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')
        ->amount(49_90)->create(['name' => 'Langganan Figma']);

    $owner->notify(new RecurringCostDue($item));

    $data = $owner->notifications()->firstOrFail()->data;

    expect($data['key'])->toBe('notification.recurring_cost_due')
        ->and($data['params']['name'])->toBe('Langganan Figma')
        ->and($data['params']['date'])->toBe('2026-10-20')
        // The amount is sen, not a formatted string in one language.
        ->and($data['params']['amount_sen'])->toBe(49_90)
        ->and($data['tone'])->toBe('warning')
        // Nothing in the row is a translated sentence.
        ->and($data)->not->toHaveKey('message');
});

it('serves the bell menu the key and parameters, whatever language is active', function () {
    $owner = User::factory()->create(['locale' => Locale::Malay]);
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')->create();

    $owner->notify(new RecurringCostDue($item));

    // Written while the app was in Malay; read while it is in English.
    $owner->update(['locale' => Locale::English]);

    $this->actingAs($owner)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('notifications.0.key', 'notification.recurring_cost_due')
        ->assertJsonPath('notifications.0.read', false)
        ->assertJsonPath('unread', 1)
        // The row still carries the key, so the page renders it in English.
        ->assertJsonStructure([
            'notifications' => [['id', 'key', 'params', 'url', 'tone', 'read', 'created_at']],
            'unread',
        ]);
});

it('links a drafted invoice notification to that invoice', function () {
    $owner = User::factory()->create();
    $client = Client::factory()->create();
    $item = RecurringItem::factory()->clientBilling($client)->autoGenerating()
        ->dueOn('2026-10-20')->amount(500_00)->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'recurring_item_id' => $item->id,
    ]);

    $owner->notify(new RecurringInvoiceDrafted($item, $invoice));

    $data = $owner->notifications()->firstOrFail()->data;

    expect($data['key'])->toBe('notification.recurring_invoice_drafted')
        ->and($data['params']['number'])->toBe($invoice->number)
        ->and($data['url'])->toContain('/invoices/'.$invoice->id);
});

it('e-mails the reminder in the language the owner reads', function () {
    Mail::fake();

    // HasLocalePreference on the user is what makes this work from a
    // scheduled command, which has no request language of its own.
    $malay = User::factory()->create(['locale' => Locale::Malay]);
    $english = User::factory()->create(['locale' => Locale::English]);
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')
        ->create(['name' => 'Hosting']);

    $malay->notify(new RecurringCostDue($item));
    $english->notify(new RecurringCostDue($item));

    // The notification channel applies the notifiable's preferred locale
    // before rendering, so the message is built inside that locale here too.
    foreach ([[$malay, 'ms', 'akan dikenakan'], [$english, 'en', 'is due']] as [$user, $locale, $phrase]) {
        $mail = app()->setLocale($locale) ?? null;
        $mail = (new RecurringCostDue($item))->toMail($user);

        expect(implode(' ', $mail->introLines))->toContain($phrase)
            ->and($user->preferredLocale())->toBe($locale);
    }

    app()->setLocale('ms');
});

it('marks one notification as read', function () {
    $owner = User::factory()->create();
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')->create();
    $owner->notify(new RecurringCostDue($item));

    $id = $owner->notifications()->firstOrFail()->id;

    $this->actingAs($owner)
        ->from(route('dashboard'))
        ->post(route('notifications.read', $id))
        ->assertRedirect(route('dashboard'));

    expect($owner->unreadNotifications()->count())->toBe(0);
});

it('marks every notification as read', function () {
    $owner = User::factory()->create();
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')->create();

    $owner->notify(new RecurringCostDue($item));
    $owner->notify(new RecurringCostDue($item));

    $this->actingAs($owner)
        ->from(route('dashboard'))
        ->post(route('notifications.read-all'))
        ->assertRedirect(route('dashboard'));

    expect($owner->unreadNotifications()->count())->toBe(0)
        ->and($owner->notifications()->count())->toBe(2);
});

it('shares the unread count with every page', function () {
    $owner = User::factory()->create();
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')->create();

    $owner->notify(new RecurringCostDue($item));
    $owner->notify(new RecurringCostDue($item));

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.unreadNotifications', 2));
});

it('will not read another user notification', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')->create();

    $other->notify(new RecurringCostDue($item));
    $id = $other->notifications()->firstOrFail()->id;

    $this->actingAs($owner)
        ->from(route('dashboard'))
        ->post(route('notifications.read', $id));

    expect($other->unreadNotifications()->count())->toBe(1);
});

it('reads the same stored notification in either language', function () {
    $owner = User::factory()->create(['locale' => Locale::Malay]);
    $item = RecurringItem::factory()->ownCost()->dueOn('2026-10-20')
        ->amount(68_00)->create(['name' => 'Langganan Figma']);

    $owner->notify(new RecurringCostDue($item));

    $data = $owner->notifications()->firstOrFail()->data;

    // What the bell menu builds from the stored key. The page formats the
    // amount and the date for the reader; these stand in for that.
    $render = fn (string $locale) => __($data['key'], [
        'name' => $data['params']['name'],
        'amount' => Money::format($data['params']['amount_sen'], $locale),
        'date' => DocumentDate::long(
            CarbonImmutable::parse($data['params']['date']),
            $locale,
        ),
    ], $locale);

    expect($render('ms'))
        ->toContain('Langganan Figma')
        ->toContain('akan dikenakan pada')
        ->toContain('20 Okt 2026')
        ->and($render('en'))
        ->toContain('Langganan Figma')
        ->toContain('is due on')
        ->toContain('20 Oct 2026');
});

it('builds the drafted invoice sentence in either language', function () {
    $owner = User::factory()->create();
    $client = Client::factory()->create(['company' => 'Wei Trading Sdn Bhd']);
    $item = RecurringItem::factory()->clientBilling($client)
        ->dueOn('2026-10-20')->create(['name' => 'Hosting tahunan']);
    $invoice = Invoice::factory()->totalling(1_200_00)->create([
        'client_id' => $client->id,
        'recurring_item_id' => $item->id,
    ]);

    $owner->notify(new RecurringInvoiceDrafted($item, $invoice));

    $data = $owner->notifications()->firstOrFail()->data;

    $render = fn (string $locale) => __($data['key'], [
        ...$data['params'],
        'amount' => Money::format($data['params']['amount_sen'], $locale),
    ], $locale);

    expect($render('ms'))->toContain('Invois draf')
        ->toContain('Wei Trading Sdn Bhd')
        ->and($render('en'))->toContain('Draft invoice')
        ->toContain('Wei Trading Sdn Bhd');
});
