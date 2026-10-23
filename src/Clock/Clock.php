<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Clock;

interface Clock
{
    /**
     * The current Unix time, in seconds.
     */
    public function now(): int;

    public function sleep(int $seconds): void;
}
