<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Authorization;

use Closure;
use Jubianchi\DevNull\ApiException;
use Jubianchi\DevNull\Client;
use Jubianchi\DevNull\Clock\Clock;

/**
 * The device authorization grant of RFC 8628: the user types a short code on the web, while the terminal polls.
 */
final readonly class DeviceCodeFlow implements AuthorizationFlow
{
    private const int SLOW_DOWN_SECONDS = 5;

    /**
     * @param Closure(DeviceAuthorization): void $show tells the user where to go and which code to type
     */
    public function __construct(
        private Client $client,
        private Closure $show,
        private Clock $clock,
    ) {}

    public function authorize(string $deviceName): array
    {
        try {
            $authorization = $this->client->startDeviceAuthorization($deviceName);
        } catch (ApiException $e) {
            throw $e->status === 404 ? new FlowUnavailable('The server has no device authorization grant.', previous: $e) : $e;
        }

        ($this->show)($authorization);

        $deadline = $this->clock->now() + $authorization->expiresIn;
        $interval = $authorization->interval;

        while (true) {
            if ($this->clock->now() + $interval > $deadline) {
                throw new AuthorizationFailed("The code {$authorization->userCode} expired before it was approved. Start again.");
            }

            $this->clock->sleep($interval);

            try {
                return $this->client->redeemDeviceCode($authorization->deviceCode);
            } catch (ApiException $e) {
                $interval = match ($e->errorCode) {
                    'authorization_pending' => $interval,
                    'slow_down' => max($interval + self::SLOW_DOWN_SECONDS, is_int($e->details['interval'] ?? null) ? $e->details['interval'] : 0),
                    'access_denied', 'invalid_grant' => throw new AuthorizationFailed($e->reason, previous: $e),
                    default => throw $e,
                };
            }
        }
    }
}
