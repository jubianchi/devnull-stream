<?php

declare(strict_types=1);

use Jubianchi\DevNull\Signing\Payload;

it('builds the payload of a CLI commit', function (): void {
    expect(Payload::commit(1760000000, str_repeat('ab', 16), '', "  Hello\nworld \n"))
        ->toBe("1760000000\n" . str_repeat('ab', 16) . "\n\nHello\nworld");
});

it('builds the payload of a CLI reply', function (): void {
    expect(Payload::commit(1760000000, 'n', '01a1680b', 'Agreed!'))->toBe("1760000000\nn\n01a1680b\nAgreed!");
});

it('builds the payload of a signed API request', function (): void {
    expect(Payload::api(1760000000, 'n', 'POST', '/api/v1/devices?x=1', '{"code":"c"}'))
        ->toBe("1760000000\nn\nPOST\n/api/v1/devices?x=1\n{\"code\":\"c\"}");
});

it('builds the payload of a signed API request without body', function (): void {
    expect(Payload::api(1760000000, 'n', 'DELETE', '/api/v1/commits/abc/star', ''))
        ->toBe("1760000000\nn\nDELETE\n/api/v1/commits/abc/star\n");
});

it('makes 32 hex characters nonces', function (): void {
    expect(Payload::nonce())->toMatch('/^[0-9a-f]{32}$/')
        ->and(Payload::nonce())->not->toBe(Payload::nonce());
});
