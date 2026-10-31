<?php

namespace App\Notifications;

use App\Models\RecurringItem;
use App\Notifications\Concerns\RendersInReadersLanguage;
use App\Support\DocumentDate;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A cost the business pays itself is coming up.
 */
class RecurringCostDue extends Notification
{
    use Queueable, RendersInReadersLanguage;

    public function __construct(public RecurringItem $item) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload(
            key: 'notification.recurring_cost_due',
            params: [
                'name' => $this->item->name,
                'amount_sen' => $this->item->amount,
                'date' => $this->item->next_date->toDateString(),
            ],
            url: route('recurring.index', ['kind' => 'own_cost']),
            tone: 'warning',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // The mailer applies the user's preferred locale, so these read in
        // whichever language the owner has chosen.
        $locale = app()->getLocale();

        return (new MailMessage)
            ->subject(__('mail.recurring_cost.subject', [
                'name' => $this->item->name,
            ]))
            ->line(__('mail.recurring_cost.line', [
                'name' => $this->item->name,
                'amount' => Money::format($this->item->amount, $locale),
                'date' => DocumentDate::long($this->item->next_date, $locale),
            ]))
            ->action(
                __('recurring.title'),
                route('recurring.index', ['kind' => 'own_cost']),
            );
    }
}
