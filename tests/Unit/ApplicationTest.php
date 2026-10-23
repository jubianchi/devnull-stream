<?php

declare(strict_types=1);

use Jubianchi\DevNull\Authorization\Pkce;
use Jubianchi\DevNull\Console\Application;
use Jubianchi\DevNull\Tests\Fakes\FakeClock;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

/**
 * Runs the command against a server without device authorizations, unless the transport says otherwise.
 *
 * @param list<string> $argv
 * @return array{int, string, FakeTransport}
 */
function runApplication(array $argv, string $input = '', ?FakeTransport $transport = null): array
{
    $transport ??= new FakeTransport()->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']], 201);
    $in = fopen('php://memory', 'r+') ?: throw new RuntimeException('No memory stream.');
    $err = fopen('php://memory', 'r+') ?: throw new RuntimeException('No memory stream.');
    fwrite($in, $input);
    rewind($in);

    $application = new Application(
        client($transport, key: 'ssh-ed25519 AAAA'),
        new FakeClock(),
        static fn(): Pkce => new Pkce(str_repeat('v', 43), 'the-state'),
    );
    $status = $application->run($argv, $in, $err);
    rewind($err);

    return [$status, (string) stream_get_contents($err), $transport];
}

function deviceAuthorizations(): FakeTransport
{
    return new FakeTransport()->on('POST', '/api/v1/device-authorizations', [
        'device_code' => 'the-device-code',
        'user_code' => 'WDJB-MJHT',
        'verification_uri' => 'https://dev-null.test/activate',
        'verification_uri_complete' => 'https://dev-null.test/activate?user_code=WDJB-MJHT',
        'expires_in' => 900,
        'interval' => 5,
    ], 201);
}

it('authorizes a device with a code typed on the web', function (): void {
    $transport = deviceAuthorizations()
        ->on('POST', '/api/v1/devices', ['message' => 'Waiting.', 'code' => 'authorization_pending'], 400)
        ->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']]);

    [$status, $output] = runApplication(['devnull', 'authorize', 'My', 'laptop'], '', $transport);

    expect($status)->toBe(0)
        ->and($output)->toContain('https://dev-null.test/activate?user_code=WDJB-MJHT')
        ->and($output)->toContain('https://dev-null.test/activate', 'WDJB-MJHT')
        ->and($output)->toContain('Waiting for the approval')
        ->and($output)->toContain('Approved as a device of @me.')
        ->and($output)->not->toContain('devnull://authorize')
        ->and($transport->requests[0]->body)->toBe('{"device_name":"My laptop"}');
});

it('fails when the device is denied', function (): void {
    $transport = deviceAuthorizations()
        ->on('POST', '/api/v1/devices', ['message' => 'The device was denied on the web.', 'code' => 'access_denied'], 400);

    [$status, $output] = runApplication(['devnull', 'authorize'], '', $transport);

    expect($status)->toBe(1)
        ->and($output)->toContain('The device was denied on the web.');
});

it('falls back on the pasted link when the server has no device authorizations', function (): void {
    [$status, $output, $transport] = runApplication(['devnull', 'authorize', 'My', 'laptop'], "devnull://authorize?code=c&state=the-state\n");

    expect($status)->toBe(0)
        ->and($output)->toContain('1. Open and approve:', 'https://dev-null.test/mobile/authorize?', 'device_name=My+laptop')
        ->and($output)->toContain('copy it from the address bar', 'Location header')
        ->and($output)->toContain('Approved as a device of @me.')
        ->and($transport->last()->url)->toBe(TEST_URL . '/api/v1/devices');
});

it('names the device after the wrapper by default', function (): void {
    [, $output] = runApplication(['devnull', 'authorize'], "devnull://authorize?code=c&state=the-state\n");

    expect($output)->toContain('device_name=PHP+stream+wrapper');
});

it('fails on a wrong link', function (): void {
    [$status, $output, $transport] = runApplication(['devnull', 'authorize'], "devnull://authorize?code=c&state=wrong\n");

    expect($status)->toBe(1)
        ->and($output)->toContain('That is not the devnull://authorize?code=… link this approval sent back.')
        ->and(array_map(fn($request) => $request->url, $transport->requests))->toBe([TEST_URL . '/api/v1/device-authorizations']);
});

it('shows its usage', function (string $command): void {
    [$status, $output] = runApplication($command === '' ? ['devnull'] : ['devnull', $command]);

    expect($status)->toBe(1)
        ->and($output)->toContain('Usage: devnull authorize [device name]');
})->with([
    'no command' => '',
    'unknown command' => 'push',
]);
