<?php

declare(strict_types=1);

use Jubianchi\DevNull\ApiException;
use Jubianchi\DevNull\Authorization\AuthorizationFailed;
use Jubianchi\DevNull\Authorization\DeviceAuthorization;
use Jubianchi\DevNull\Authorization\DeviceCodeFlow;
use Jubianchi\DevNull\Authorization\FlowUnavailable;
use Jubianchi\DevNull\Tests\Fakes\FakeClock;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

/**
 * @param array<string, mixed> $overrides
 */
function started(FakeTransport $transport, array $overrides = []): FakeTransport
{
    return $transport->on('POST', '/api/v1/device-authorizations', [
        'device_code' => 'the-device-code',
        'user_code' => 'WDJB-MJHT',
        'verification_uri' => 'https://dev-null.test/activate',
        'verification_uri_complete' => 'https://dev-null.test/activate?user_code=WDJB-MJHT',
        'expires_in' => 900,
        'interval' => 5,
        ...$overrides,
    ], 201);
}

/**
 * @return array<string, string>
 */
function pending(): array
{
    return ['message' => 'Waiting for the device to be approved on the web.', 'code' => 'authorization_pending'];
}

/**
 * @param list<DeviceAuthorization> $shown
 */
function deviceCodeFlow(FakeTransport $transport, FakeClock $clock, array &$shown = []): DeviceCodeFlow
{
    return new DeviceCodeFlow(
        client($transport, key: '/keys/device'),
        static function (DeviceAuthorization $authorization) use (&$shown): void {
            $shown[] = $authorization;
        },
        $clock,
    );
}

/**
 * @return list<string>
 */
function polls(FakeTransport $transport): array
{
    return array_values(array_map(
        static fn($request): string => $request->body,
        array_filter($transport->requests, static fn($request): bool => str_ends_with($request->url, '/api/v1/devices')),
    ));
}

it('shows the code, then polls at the interval until the user approves, slowing down when told to', function (): void {
    $transport = started(new FakeTransport())
        ->on('POST', '/api/v1/devices', pending(), 400)
        ->on('POST', '/api/v1/devices', ['message' => 'Asking too often.', 'code' => 'slow_down', 'interval' => 10], 400)
        ->on('POST', '/api/v1/devices', pending(), 400)
        ->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']]);
    $clock = new FakeClock();
    $shown = [];

    $me = deviceCodeFlow($transport, $clock, $shown)->authorize('My laptop');

    expect($me)->toBe(['data' => ['username' => 'me']])
        ->and($shown)->toHaveCount(1)
        ->and($shown[0]->userCode)->toBe('WDJB-MJHT')
        ->and($clock->sleeps)->toBe([5, 5, 10, 10])
        ->and(polls($transport))->toBe(array_fill(0, 4, '{"device_code":"the-device-code"}'));
});

it('waits 5 more seconds on slow_down when the server does not say how long', function (): void {
    $transport = started(new FakeTransport())
        ->on('POST', '/api/v1/devices', ['message' => 'Asking too often.', 'code' => 'slow_down'], 400)
        ->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']]);
    $clock = new FakeClock();

    deviceCodeFlow($transport, $clock)->authorize('PHP');

    expect($clock->sleeps)->toBe([5, 10]);
});

it('stops when the user denies the device', function (): void {
    $transport = started(new FakeTransport())
        ->on('POST', '/api/v1/devices', pending(), 400)
        ->on('POST', '/api/v1/devices', ['message' => 'The device was denied on the web.', 'code' => 'access_denied'], 400);

    expect(fn() => deviceCodeFlow($transport, new FakeClock())->authorize('PHP'))
        ->toThrow(AuthorizationFailed::class, 'The device was denied on the web.')
        ->and(polls($transport))->toHaveCount(2);
});

it('stops when the server no longer knows the code', function (): void {
    $transport = started(new FakeTransport())
        ->on('POST', '/api/v1/devices', ['message' => 'This sign-in has expired or was already used.', 'code' => 'invalid_grant'], 400);

    deviceCodeFlow($transport, new FakeClock())->authorize('PHP');
})->throws(AuthorizationFailed::class, 'This sign-in has expired or was already used.');

it('gives up when the code expires before the user decides', function (): void {
    $transport = started(new FakeTransport(), ['expires_in' => 12])->on('POST', '/api/v1/devices', pending(), 400);
    $clock = new FakeClock();

    expect(fn() => deviceCodeFlow($transport, $clock)->authorize('PHP'))
        ->toThrow(AuthorizationFailed::class, 'The code WDJB-MJHT expired before it was approved. Start again.')
        ->and($clock->sleeps)->toBe([5, 5])
        ->and(polls($transport))->toHaveCount(2);
});

it('is unavailable when the server has no device authorizations', function (): void {
    $transport = new FakeTransport();

    expect(fn() => deviceCodeFlow($transport, new FakeClock())->authorize('PHP'))
        ->toThrow(FlowUnavailable::class)
        ->and($transport->requests)->toHaveCount(1);
});

it('reports the other errors as they are', function (): void {
    $start = new FakeTransport()->on('POST', '/api/v1/device-authorizations', ['message' => 'Too Many Attempts.', 'code' => 'rate_limited'], 429);
    $poll = started(new FakeTransport())->on('POST', '/api/v1/devices', ['message' => 'Server Error'], 500);

    expect(fn() => deviceCodeFlow($start, new FakeClock())->authorize('PHP'))->toThrow(ApiException::class, 'Too Many Attempts.')
        ->and(fn() => deviceCodeFlow($poll, new FakeClock())->authorize('PHP'))->toThrow(ApiException::class, 'Server Error');
});
