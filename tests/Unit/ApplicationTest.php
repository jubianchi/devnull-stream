<?php

declare(strict_types=1);

use Jubianchi\DevNull\Authorization\Pkce;
use Jubianchi\DevNull\Console\Application;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

/**
 * @param list<string> $argv
 * @return array{int, string, FakeTransport}
 */
function runApplication(array $argv, string $input = ''): array
{
    $transport = new FakeTransport()->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']], 201);
    $in = fopen('php://memory', 'r+') ?: throw new RuntimeException('No memory stream.');
    $err = fopen('php://memory', 'r+') ?: throw new RuntimeException('No memory stream.');
    fwrite($in, $input);
    rewind($in);

    $application = new Application(client($transport, key: 'ssh-ed25519 AAAA'), static fn(): Pkce => new Pkce(str_repeat('v', 43), 'the-state'));
    $status = $application->run($argv, $in, $err);
    rewind($err);

    return [$status, (string) stream_get_contents($err), $transport];
}

it('authorizes a device from the pasted link', function (): void {
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
        ->and($transport->requests)->toBe([]);
});

it('shows its usage', function (string $command): void {
    [$status, $output] = runApplication($command === '' ? ['devnull'] : ['devnull', $command]);

    expect($status)->toBe(1)
        ->and($output)->toContain('Usage: devnull authorize [device name]');
})->with([
    'no command' => '',
    'unknown command' => 'push',
]);
