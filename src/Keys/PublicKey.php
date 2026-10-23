<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Keys;

final class PublicKey
{
    /**
     * "<type> <base64>" from the first line of a public key or `ssh-add -L` output, without the comment.
     */
    public static function id(string $line): string
    {
        $first = trim(explode("\n", trim($line))[0]);
        $fields = preg_split('/\s+/', $first) ?: [];

        return implode(' ', array_slice($fields, 0, 2));
    }

    public static function fingerprint(string $publicKey): string
    {
        $blob = base64_decode(explode(' ', self::id($publicKey))[1] ?? '', true);

        return 'SHA256:' . rtrim(base64_encode(hash('sha256', $blob === false ? '' : $blob, true)), '=');
    }

    public static function isSupported(string $id): bool
    {
        return preg_match('/^(ssh-ed25519|ssh-rsa) /', $id) === 1;
    }
}
