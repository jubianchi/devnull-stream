<?php

declare(strict_types=1);

use Jubianchi\DevNull\ApiException;
use Jubianchi\DevNull\Authorization\DeviceAuthorization;
use Jubianchi\DevNull\Tests\Fakes\FakeSigner;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

const STARTED = [
    'device_code' => 'the-device-code',
    'user_code' => 'WDJB-MJHT',
    'verification_uri' => 'https://dev-null.test/activate',
    'verification_uri_complete' => 'https://dev-null.test/activate?user_code=WDJB-MJHT',
    'expires_in' => 900,
    'interval' => 5,
];

it('starts a device authorization, signed by the key to approve', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/v1/device-authorizations', STARTED, 201);
    $signer = new FakeSigner();

    $authorization = client($transport, $signer, key: '/keys/device')->startDeviceAuthorization('My laptop');

    expect($authorization)->toEqual(new DeviceAuthorization(
        'the-device-code',
        'WDJB-MJHT',
        'https://dev-null.test/activate',
        'https://dev-null.test/activate?user_code=WDJB-MJHT',
        900,
        5,
    ))
        ->and($transport->last()->body)->toBe('{"device_name":"My laptop"}')
        ->and($signer->signed[0]['namespace'])->toBe('devnull-api')
        ->and($signer->signed[0]['key'])->toBe('/keys/device');
});

it('refuses a start answer it does not understand', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/v1/device-authorizations', ['device_code' => 'x'], 201);

    client($transport, key: '/keys/device')->startDeviceAuthorization('PHP');
})->throws(UnexpectedValueException::class, 'devnull: unexpected answer to POST /api/v1/device-authorizations.');

it('redeems a device code', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']]);

    expect(client($transport, key: '/keys/device')->redeemDeviceCode('the-device-code'))->toBe(['data' => ['username' => 'me']])
        ->and($transport->last()->body)->toBe('{"device_code":"the-device-code"}');
});

it('answers several times in turn, then repeats its last answer', function (): void {
    $transport = new FakeTransport()
        ->on('POST', '/api/v1/devices', ['code' => 'authorization_pending', 'message' => 'Waiting.'], 400)
        ->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']]);
    $client = client($transport, key: '/keys/device');

    expect(fn() => $client->redeemDeviceCode('c'))->toThrow(ApiException::class, 'Waiting.')
        ->and($client->redeemDeviceCode('c'))->toBe(['data' => ['username' => 'me']])
        ->and($client->redeemDeviceCode('c'))->toBe(['data' => ['username' => 'me']]);
});
