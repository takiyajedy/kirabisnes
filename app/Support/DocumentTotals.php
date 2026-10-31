<?php

namespace App\Support;

/**
 * Adds a document's line items up.
 *
 * All integer sen, so a long invoice cannot drift by a cent (PLAN.md 3.1).
 * The line amount is worked out here and stored on the row, which means a
 * document issued last year keeps the figures it was issued with.
 */
final class DocumentTotals
{
    /**
     * The amount of one line: quantity x unit price, rounded to the sen.
     *
     * The quantity is the one decimal quantity in the system — 2.5 hours is
     * a real thing to invoice for — so it is multiplied as hundredths and
     * divided back out, keeping the arithmetic in integers.
     */
    public static function lineAmount(string|float|int $quantity, int $unitPrice): int
    {
        $hundredths = (int) round(((float) $quantity) * 100);

        return intdiv($hundredths * $unitPrice + 50, 100);
    }

    /**
     * The rows come straight from a form, so they are read defensively: a
     * line with no quantity is one of one, and a line with no price is free.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: int, discount: int, total: int, items: array<int, array<string, mixed>>}
     */
    public static function forItems(array $items, int $discount = 0): array
    {
        $priced = [];
        $subtotal = 0;

        foreach (array_values($items) as $index => $item) {
            $quantity = $item['quantity'] ?? 1;
            $amount = self::lineAmount(
                is_string($quantity) || is_float($quantity) || is_int($quantity) ? $quantity : 1,
                (int) ($item['unit_price'] ?? 0),
            );

            $priced[] = [
                ...$item,
                'amount' => $amount,
                'sort_order' => $index,
            ];

            $subtotal += $amount;
        }

        // A discount larger than the work is almost certainly a typo, and a
        // negative total is not a document anyone can act on.
        $discount = max(0, min($discount, $subtotal));

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
            'items' => $priced,
        ];
    }
}
