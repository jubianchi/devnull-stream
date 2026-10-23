<?php

declare(strict_types=1);

use Jubianchi\DevNull\ApiException;
use Jubianchi\DevNull\Tests\Fakes\FakeKeyRing;
use Jubianchi\DevNull\Tests\Fakes\FakeSigner;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

it('calls the signed API with the configured key', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']], 201);
    $signer = new FakeSigner();

    $response = client($transport, $signer, key: '/keys/device')->signed('POST', '/api/v1/devices', ['code' => 'c', 'code_verifier' => 'v']);

    $request = $transport->last();
    $timestamp = $request->header('X-Devnull-Timestamp');
    $nonce = $request->header('X-Devnull-Nonce');

    expect($response)->toBe(['data' => ['username' => 'me']])
        ->and($timestamp)->toMatch('/^\d{10}$/')
        ->and($nonce)->toMatch('/^[0-9a-f]{32}$/')
        ->and($signer->signed)->toBe([[
            'message' => "{$timestamp}\n{$nonce}\nPOST\n/api/v1/devices\n{\"code\":\"c\",\"code_verifier\":\"v\"}",
            'namespace' => 'devnull-api',
            'key' => '/keys/device',
        ]])
        ->and($request->body)->toBe('{"code":"c","code_verifier":"v"}')
        ->and($request->header('Content-Type'))->toBe('application/json')
        ->and($request->header('X-SSH-Signature'))->toBe(base64_encode('devnull-api:/keys/device'));
});

it('signs requests without body', function (): void {
    $transport = new FakeTransport()->on('DELETE', '/api/v1/commits/abc/star', '', 204);
    $signer = new FakeSigner();

    expect(client($transport, $signer, key: '/keys/device')->signed('DELETE', '/api/v1/commits/abc/star'))->toBe([])
        ->and($signer->signed[0]['message'])->toEndWith("\nDELETE\n/api/v1/commits/abc/star\n")
        ->and($transport->last()->body)->toBe('');
});

it("falls back on the key ring's first key, without any lookup", function (): void {
    $transport = new FakeTransport()->on('POST', '/api/v1/commits/abc/star', ['data' => []]);
    $signer = new FakeSigner();

    client($transport, $signer, new FakeKeyRing(['ssh-ed25519 AAAA' => 'ssh-ed25519 AAAA', 'ssh-rsa BBBB' => '/id_rsa']))
        ->signed('POST', '/api/v1/commits/abc/star');

    expect($transport->requests)->toHaveCount(1)
        ->and($signer->signed[0]['key'])->toBe('ssh-ed25519 AAAA');
});

it('needs a device key', function (): void {
    client(new FakeTransport())->signed('POST', '/api/v1/commits/abc/star');
})->throws(RuntimeException::class, 'Set DEVNULL_KEY to the key approved as a device.');

it('reports unapproved keys', function (): void {
    $transport = new FakeTransport()->on('GET', '/api/v1/me', ['message' => "This key isn't approved."], 401);

    client($transport, key: '/keys/device')->signed('GET', '/api/v1/me');
})->throws(ApiException::class, "devnull: GET /api/v1/me failed (HTTP 401): This key isn't approved.");
