<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Authorization;

/**
 * Proof Key for Code Exchange (RFC 7636, S256), as the mobile app's sign-in uses it.
 */
final readonly class Pkce
{
    public function __construct(
        public string $verifier,
        public string $state,
    ) {}

    public static function generate(): self
    {
        return new self(self::base64Url(random_bytes(48)), bin2hex(random_bytes(16)));
    }

    public function challenge(): string
    {
        return self::base64Url(hash('sha256', $this->verifier, true));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
