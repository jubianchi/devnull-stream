<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Keys;

interface KeyRing
{
    /**
     * Maps each usable public key, as "<type> <base64>", to what the signer signs with: the private key file, or
     * the public key itself when only the SSH agent holds it.
     *
     * @return array<string, string>
     */
    public function keys(): array;

    /**
     * The "<type> <base64>" public key of a key returned by keys() or given as DEVNULL_KEY.
     */
    public function publicKeyOf(string $key): string;
}
