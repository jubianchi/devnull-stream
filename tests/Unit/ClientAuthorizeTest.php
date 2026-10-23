<?php

declare(strict_types=1);

use Jubianchi\DevNull\Authorization\Pkce;
use Jubianchi\DevNull\Keys\PublicKey;
use Jubianchi\DevNull\Tests\Fakes\FakeKeyRing;
use Jubianchi\DevNull\Tests\Fakes\FakeSigner;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

const DEVICE_KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOaaVDQ4CTbmcYWlhHpOBLOAo2Kg5LZNHRmDN04M7sTE';

it('approves the key with the sign-in page, then redeems the code it sends back', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']], 201);
    $signer = new FakeSigner();
    $pkce = new Pkce('dBjftJeZ4CVP-mJ92K1s3N8r3UHB0WbZg3tAyq3z6YI', 'the-state');
    $asked = null;

    $me = client($transport, $signer, new FakeKeyRing([DEVICE_KEY => '/home/me/.ssh/id_ed25519']))->authorize(
        'My laptop',
        function (string $url) use (&$asked): string {
            $asked = $url;

            return " devnull://authorize?code=the-code&state=the-state\n";
        },
        $pkce,
    );

    parse_str((string) parse_url((string) $asked, PHP_URL_QUERY), $query);

    expect($me)->toBe(['data' => ['username' => 'me']])
        ->and((string) $asked)->toStartWith('https://dev-null.test/mobile/authorize?')
        ->and($query)->toBe([
            'fingerprint' => PublicKey::fingerprint(DEVICE_KEY),
            'device_name' => 'My laptop',
            'code_challenge' => '6LiCYNASrOLLDspnWP33XV0xvYwBiWECHDYC2fqDESk',
            'code_challenge_method' => 'S256',
            'state' => 'the-state',
            'redirect_uri' => 'devnull://authorize',
        ])
        ->and($transport->last()->body)->toBe('{"code":"the-code","code_verifier":"dBjftJeZ4CVP-mJ92K1s3N8r3UHB0WbZg3tAyq3z6YI"}')
        ->and($signer->signed[0]['key'])->toBe('/home/me/.ssh/id_ed25519');
});

it('refuses links that are not the answer to this approval', function (string $link): void {
    $transport = new FakeTransport();

    expect(fn() => client($transport, key: DEVICE_KEY)->authorize('PHP', fn(): string => $link, new Pkce(str_repeat('v', 43), 'the-state')))
        ->toThrow(RuntimeException::class, 'That is not the devnull://authorize?code=… link this approval sent back.')
        ->and($transport->requests)->toBe([]);
})->with([
    'another state' => 'devnull://authorize?code=c&state=other',
    'no code' => 'devnull://authorize?state=the-state',
    'nothing' => '',
]);
