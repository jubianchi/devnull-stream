<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Authorization;

use UnexpectedValueException;

/**
 * A started device authorization grant (RFC 8628): the code the user types on the web, and how to poll for it.
 */
final readonly class DeviceAuthorization
{
    public function __construct(
        public string $deviceCode,
        public string $userCode,
        public string $verificationUri,
        public string $verificationUriComplete,
        public int $expiresIn,
        public int $interval,
    ) {}

    /**
     * @param array<mixed> $answer
     */
    public static function fromAnswer(array $answer, string $what): self
    {
        $strings = array_filter(
            array_intersect_key($answer, array_flip(['device_code', 'user_code', 'verification_uri', 'verification_uri_complete'])),
            is_string(...),
        );
        $expiresIn = $answer['expires_in'] ?? null;
        $interval = $answer['interval'] ?? null;

        if (count($strings) !== 4 || !is_int($expiresIn) || !is_int($interval)) {
            throw new UnexpectedValueException("devnull: unexpected answer to {$what}.");
        }

        return new self(
            $strings['device_code'],
            $strings['user_code'],
            $strings['verification_uri'],
            $strings['verification_uri_complete'],
            $expiresIn,
            $interval,
        );
    }
}
