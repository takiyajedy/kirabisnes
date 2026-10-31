<?php

use App\Support\DocumentDate;
use Carbon\CarbonImmutable;

it('formats a date in Malay', function () {
    expect(DocumentDate::long(CarbonImmutable::parse('2026-10-30'), 'ms'))
        ->toBe('30 Okt 2026');
});

it('formats a date in English', function () {
    expect(DocumentDate::long(CarbonImmutable::parse('2026-10-30'), 'en'))
        ->toBe('30 Oct 2026');
});

it('uses the same Malay month names the browser does', function () {
    // The screen formats with Intl.DateTimeFormat('ms-MY'), which follows
    // CLDR: August is "Ogo". Carbon's own Malay locale says "Ogs", so a
    // document formatted with Carbon would disagree with the page it was
    // created on.
    expect(DocumentDate::long(CarbonImmutable::parse('2026-08-04'), 'ms'))
        ->toBe('4 Ogo 2026');
});

it('covers every month without a leading zero on the day', function () {
    $months = collect(range(1, 12))->map(
        fn (int $month) => DocumentDate::long(
            CarbonImmutable::create(2026, $month, 5),
            'ms',
        ),
    );

    expect($months->all())->toBe([
        '5 Jan 2026', '5 Feb 2026', '5 Mac 2026', '5 Apr 2026',
        '5 Mei 2026', '5 Jun 2026', '5 Jul 2026', '5 Ogo 2026',
        '5 Sep 2026', '5 Okt 2026', '5 Nov 2026', '5 Dis 2026',
    ]);
});
