<?php

use App\Support\DocumentTotals;

it('multiplies a whole quantity by the unit price', function () {
    // 3 x RM250.00
    expect(DocumentTotals::lineAmount(3, 250_00))->toBe(750_00);
});

it('handles a fractional quantity without floats', function () {
    // 2.5 hours at RM120.00
    expect(DocumentTotals::lineAmount('2.5', 120_00))->toBe(300_00);
});

it('rounds a half sen up', function () {
    // 0.5 x 1 sen = half a sen.
    expect(DocumentTotals::lineAmount('0.5', 1))->toBe(1)
        // 0.33 x 1 sen rounds down to nothing.
        ->and(DocumentTotals::lineAmount('0.33', 1))->toBe(0);
});

it('keeps an awkward rate exact', function () {
    // 7 x RM33.33 — a float would land on 233.30999999999997
    expect(DocumentTotals::lineAmount(7, 33_33))->toBe(233_31);
});

it('adds the lines up into a subtotal and total', function () {
    $totals = DocumentTotals::forItems([
        ['description' => 'Reka bentuk', 'quantity' => 1, 'unit_price' => 1_500_00],
        ['description' => 'Pembangunan', 'quantity' => '2.5', 'unit_price' => 1_200_00],
    ]);

    expect($totals['subtotal'])->toBe(4_500_00)
        ->and($totals['discount'])->toBe(0)
        ->and($totals['total'])->toBe(4_500_00);
});

it('takes a discount off the total but leaves the subtotal alone', function () {
    $totals = DocumentTotals::forItems([
        ['description' => 'Kerja', 'quantity' => 1, 'unit_price' => 1_000_00],
    ], 150_00);

    expect($totals['subtotal'])->toBe(1_000_00)
        ->and($totals['discount'])->toBe(150_00)
        ->and($totals['total'])->toBe(850_00);
});

it('will not discount more than the work is worth', function () {
    // A typo in the discount box must not produce a negative invoice.
    $totals = DocumentTotals::forItems([
        ['description' => 'Kerja', 'quantity' => 1, 'unit_price' => 500_00],
    ], 900_00);

    expect($totals['discount'])->toBe(500_00)
        ->and($totals['total'])->toBe(0);
});

it('ignores a negative discount', function () {
    $totals = DocumentTotals::forItems([
        ['description' => 'Kerja', 'quantity' => 1, 'unit_price' => 500_00],
    ], -200_00);

    expect($totals['discount'])->toBe(0)
        ->and($totals['total'])->toBe(500_00);
});

it('stores the line amount and its order on every item', function () {
    $totals = DocumentTotals::forItems([
        ['description' => 'Satu', 'quantity' => 2, 'unit_price' => 100],
        ['description' => 'Dua', 'quantity' => 1, 'unit_price' => 250],
    ]);

    expect($totals['items'][0]['amount'])->toBe(200)
        ->and($totals['items'][0]['sort_order'])->toBe(0)
        ->and($totals['items'][1]['amount'])->toBe(250)
        ->and($totals['items'][1]['sort_order'])->toBe(1);
});

it('totals nothing for a document with no lines', function () {
    $totals = DocumentTotals::forItems([], 100_00);

    expect($totals['subtotal'])->toBe(0)
        ->and($totals['discount'])->toBe(0)
        ->and($totals['total'])->toBe(0);
});

it('adds a long list of lines without drifting', function () {
    // A float subtotal of 100 lines at RM0.07 would not land on exactly 7.00.
    $items = array_fill(0, 100, [
        'description' => 'Baris',
        'quantity' => 1,
        'unit_price' => 7,
    ]);

    expect(DocumentTotals::forItems($items)['subtotal'])->toBe(700);
});
