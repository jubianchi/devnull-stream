<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Signing;

interface Signer
{
    /**
     * Returns the bare base64 of the SSH signature (no armor, no line breaks) of the message.
     *
     * @param string $key a private key file, or a public key held by the SSH agent
     */
    public function sign(string $message, string $namespace, string $key): string;
}
