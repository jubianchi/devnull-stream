<?php

declare(strict_types=1);

namespace Jubianchi\DevNull;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly int $status,
        string $message,
    ) {
        parent::__construct("devnull: {$method} {$uri} failed (HTTP {$status}): {$message}");
    }
}
