<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Signing;

/**
 * The messages dev-null.sh expects signatures of.
 */
final class Payload
{
    public const string COMMIT_NAMESPACE = 'devnull-commit';

    public const string API_NAMESPACE = 'devnull-api';

    /**
     * What `curl dev-null.sh/commit | bash` signs and posts to /api/cli/commits.
     */
    public static function commit(int $timestamp, string $nonce, string $replyTo, string $message): string
    {
        return implode("\n", [$timestamp, $nonce, $replyTo, trim($message)]);
    }

    /**
     * What the mobile app signs for each call to the signed API.
     */
    public static function api(int $timestamp, string $nonce, string $method, string $uri, string $body): string
    {
        return implode("\n", [$timestamp, $nonce, $method, $uri, $body]);
    }

    public static function nonce(): string
    {
        return bin2hex(random_bytes(16));
    }
}
