<?php

declare(strict_types=1);

use Jubianchi\DevNull\Clock\SystemClock;

it('tells the Unix time', function (): void {
    $before = time();
    $now = new SystemClock()->now();

    expect($now)->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual(time());
});

it('does not sleep for nothing', function (): void {
    $start = hrtime(true);
    new SystemClock()->sleep(-3);

    expect(hrtime(true) - $start)->toBeLessThan(500_000_000);
});
