<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Authorization;

/**
 * A way to approve the signing key as a device of the user's account.
 */
interface AuthorizationFlow
{
    /**
     * Returns what the server answers once the device is approved, as GET /api/v1/me does.
     *
     * @return array<mixed>
     *
     * @throws FlowUnavailable when the server doesn't offer this flow
     * @throws AuthorizationFailed when the user denied the device, or the approval expired
     */
    public function authorize(string $deviceName): array;
}
