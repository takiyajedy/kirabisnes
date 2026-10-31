<?php

use App\Support\Money;

it('converts ringgit input into sen', function (string|float|int|null $input, int $expected) {
    expect(Money::toSen($input))->toBe($expected);
})->with([
    ['12.50', 1250],
    ['1250', 125000],
    ['0.01', 1],
    ['0', 0],
    ['', 0],
    [null, 0],
    ['RM 1,250.00', 125000],
    ['  3,000.99 ', 300099],
    ['-45.60', -4560],
    [12.5, 1250],
    [100, 10000],
    ['100.015', 10002],   // rounds half up
    ['99.994', 9999],
    ['.', 0],
    ['-', 0],
]);

it('converts sen back into a ringgit string', function () {
    expect(Money::toRinggit(1250))->toBe('12.50')
        ->and(Money::toRinggit(1))->toBe('0.01')
        ->and(Money::toRinggit(0))->toBe('0.00')
        ->and(Money::toRinggit(125000))->toBe('1250.00');
});

it('round trips sen through ringgit without losing cents', function (int $sen) {
    expect(Money::toSen(Money::toRinggit($sen)))->toBe($sen);
})->with([0, 1, 99, 100, 1250, 300099, 999999999]);

it('formats sen as currency', function () {
    $formatted = Money::format(125000, 'ms');

    expect($formatted)->toContain('1,250.00')
        ->and($formatted)->toContain('RM');
});

it('formats zero and negative amounts', function () {
    expect(Money::format(0, 'en'))->toContain('0.00')
        ->and(Money::format(-1250, 'en'))->toContain('12.50');
});
