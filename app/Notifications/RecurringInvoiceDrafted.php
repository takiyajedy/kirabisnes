<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\RecurringItem;
use App\Notifications\Concerns\RendersInReadersLanguage;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A client billing item has drafted an invoice, ready to be checked and sent.
 */
class RecurringInvoiceDrafted extends Notification
{
    use Queueable, RendersInReadersLanguage;

    public function __construct(
        public RecurringItem $item,
        public Invoice $invoice,
    ) {}

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
            key: 'notification.recurring_invoice_drafted',
            params: [
                'number' => $this->invoice->number,
                'name' => $this->item->name,
                'client' => $this->item->client?->displayName() ?? '',
                'amount_sen' => $this->invoice->total,
            ],
            url: route('invoices.show', $this->invoice),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = app()->getLocale();

        return (new MailMessage)
            ->subject(__('mail.recurring_invoice.subject', [
                'number' => $this->invoice->number,
            ]))
            ->line(__('mail.recurring_invoice.line', [
                'number' => $this->invoice->number,
                'client' => $this->item->client?->displayName() ?? '',
                'amount' => Money::format($this->invoice->total, $locale),
            ]))
            ->action(__('invoice.one'), route('invoices.show', $this->invoice));
    }
}
