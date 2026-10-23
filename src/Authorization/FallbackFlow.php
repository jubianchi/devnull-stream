<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Authorization;

/**
 * Tries each flow in turn, moving on only when the server doesn't offer one.
 */
final readonly class FallbackFlow implements AuthorizationFlow
{
    /** @var list<AuthorizationFlow> */
    private array $flows;

    public function __construct(AuthorizationFlow ...$flows)
    {
        $this->flows = array_values($flows);
    }

    public function authorize(string $deviceName): array
    {
        $unavailable = new FlowUnavailable('The server offers none of the ways to approve a device.');

        foreach ($this->flows as $flow) {
            try {
                return $flow->authorize($deviceName);
            } catch (FlowUnavailable $e) {
                $unavailable = $e;
            }
        }

        throw $unavailable;
    }
}
