<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Authorization;

use Closure;
use Jubianchi\DevNull\Client;

/**
 * The mobile app's sign-in: the user approves on the web, then copies the devnull://authorize link it redirects to.
 */
final readonly class PkceFlow implements AuthorizationFlow
{
    /**
     * @param Closure(string): string $ask gets the page to open and returns the link it redirected to
     * @param (Closure(): Pkce)|null $pkce
     */
    public function __construct(
        private Client $client,
        private Closure $ask,
        private ?Closure $pkce = null,
    ) {}

    public function authorize(string $deviceName): array
    {
        return $this->client->authorize($deviceName, $this->ask, $this->pkce === null ? null : ($this->pkce)());
    }
}
