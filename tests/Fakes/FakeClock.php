<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Tests\Fakes;

use Jubianchi\DevNull\Clock\Clock;

/**
 * A clock whose sleeps return at once, moving its time forward instead.
 */
final class FakeClock implements Clock
{
    /** @var list<int> */
    public private(set) array $sleeps = [];

    public function __construct(
        private int $now = 1_760_000_000,
    ) {}

    public function now(): int
    {
        return $this->now;
    }

    public function sleep(int $seconds): void
    {
        $this->sleeps[] = $seconds;
        $this->now += $seconds;
    }
}
