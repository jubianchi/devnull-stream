<?php

declare(strict_types=1);

namespace Jubianchi\DevNull;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /**
     * @param string $reason the API's own message
     * @param string|null $errorCode the API's `code`, e.g. `authorization_pending`
     * @param array<mixed> $details the whole JSON answer, e.g. with the `interval` of a `slow_down`
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly int $status,
        public readonly string $reason,
        public readonly ?string $errorCode = null,
        public readonly array $details = [],
    ) {
        parent::__construct("devnull: {$method} {$uri} failed (HTTP {$status}): {$reason}");
    }
}
