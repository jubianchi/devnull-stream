<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Clock;

final readonly class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }

    public function sleep(int $seconds): void
    {
        sleep(max(0, $seconds));
    }
}
