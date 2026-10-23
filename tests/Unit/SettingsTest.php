<?php

declare(strict_types=1);

use Jubianchi\DevNull\Settings;

afterEach(function (): void {
    putenv('DEVNULL_URL');
    putenv('DEVNULL_KEY');
});

it('defaults to dev-null.sh and no key', function (): void {
    putenv('DEVNULL_URL');
    putenv('DEVNULL_KEY');

    expect(Settings::resolve([]))->toEqual(new Settings('https://dev-null.sh', null));
});

it('reads the environment', function (): void {
    putenv('DEVNULL_URL=https://staging.dev-null.test/');
    putenv('DEVNULL_KEY=/home/me/.ssh/id_ed25519');

    expect(Settings::resolve([]))->toEqual(new Settings('https://staging.dev-null.test', '/home/me/.ssh/id_ed25519'));
});

it('prefers the stream context options', function (): void {
    putenv('DEVNULL_URL=https://staging.dev-null.test');
    putenv('DEVNULL_KEY=/home/me/.ssh/id_ed25519');

    expect(Settings::resolve(['url' => 'http://localhost:8000', 'key' => 'ssh-ed25519 AAAA']))
        ->toEqual(new Settings('http://localhost:8000', 'ssh-ed25519 AAAA'));
});

it('treats empty values as unset', function (): void {
    putenv('DEVNULL_URL=');
    putenv('DEVNULL_KEY=');

    expect(Settings::resolve(['key' => '']))->toEqual(new Settings('https://dev-null.sh', null));
});
