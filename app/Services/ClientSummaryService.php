<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * What a client has been billed and what they still owe.
 *
 * A voided invoice is left out of every figure: it is a record of a document
 * that was withdrawn, not money that was ever owed.
 */
class ClientSummaryService
{
    /**
     * @return array{invoiced: int, paid: int, outstanding: int, overdue: int, invoice_count: int}
     */
    public function for(Client $client): array
    {
        return $this->all([$client->id])[$client->id] ?? self::empty();
    }

    /**
     * Every client's figures in one query, for the list page.
     *
     * @param  array<int, int>|null  $clientIds  Null for all clients.
     * @return array<int, array{invoiced: int, paid: int, outstanding: int, overdue: int, invoice_count: int}>
     */
    public function all(?array $clientIds = null): array
    {
        $rows = Invoice::query()
            ->whereNot('status', InvoiceStatus::Void->value)
            ->when($clientIds !== null, fn ($q) => $q->whereIn('client_id', $clientIds))
            ->toBase()
            ->selectRaw('client_id')
            ->selectRaw('COUNT(*) AS invoice_count')
            ->selectRaw('COALESCE(SUM(total), 0) AS invoiced')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) AS paid')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status = 'overdue' THEN total - paid_amount ELSE 0 END), 0)
                 AS overdue",
            )
            ->groupBy('client_id')
            ->get();

        $summaries = [];

        foreach ($rows as $row) {
            $invoiced = (int) $row->invoiced;
            $paid = (int) $row->paid;

            $summaries[(int) $row->client_id] = [
                'invoiced' => $invoiced,
                'paid' => $paid,
                'outstanding' => max(0, $invoiced - $paid),
                'overdue' => (int) $row->overdue,
                'invoice_count' => (int) $row->invoice_count,
            ];
        }

        return $summaries;
    }

    /**
     * Attach the figures to a collection of clients, for the list page.
     *
     * @param  Collection<int, Client>  $clients
     * @return Collection<int, Client>
     */
    public function attach(Collection $clients): Collection
    {
        $summaries = $this->all($clients->pluck('id')->all());

        return $clients->each(function (Client $client) use ($summaries): void {
            $client->summary = $summaries[$client->id] ?? self::empty();
        });
    }

    /**
     * @return array{invoiced: int, paid: int, outstanding: int, overdue: int, invoice_count: int}
     */
    public static function empty(): array
    {
        return [
            'invoiced' => 0,
            'paid' => 0,
            'outstanding' => 0,
            'overdue' => 0,
            'invoice_count' => 0,
        ];
    }
}
