<?php

declare(strict_types=1);

use Jubianchi\DevNull\ApiException;
use Jubianchi\DevNull\Tests\Fakes\FakeKeyRing;
use Jubianchi\DevNull\Tests\Fakes\FakeSigner;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

it('commits with the key dev-null.sh finds on a GitHub account', function (): void {
    $transport = new FakeTransport()
        ->on('POST', '/api/cli/key-lookups', "ssh-rsa BBBB\n")
        ->on('POST', '/api/cli/commits', "https://dev-null.test/@me/commits/abc\n", 201);
    $signer = new FakeSigner();
    $keys = new FakeKeyRing(['ssh-ed25519 AAAA' => 'ssh-ed25519 AAAA', 'ssh-rsa BBBB' => '/home/me/.ssh/id_rsa']);

    $link = client($transport, $signer, $keys)->commit("Hello from PHP!\n", '');

    [$lookup, $commit] = $transport->requests;

    expect($link)->toBe('https://dev-null.test/@me/commits/abc')
        ->and($lookup->body)->toBe("ssh-ed25519 AAAA\nssh-rsa BBBB\n")
        ->and($lookup->header('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($signer->signed)->toHaveCount(1)
        ->and($signer->signed[0]['namespace'])->toBe('devnull-commit')
        ->and($signer->signed[0]['key'])->toBe('/home/me/.ssh/id_rsa')
        ->and($signer->signed[0]['message'])->toMatch('/^\d{10}\n[0-9a-f]{32}\n\nHello from PHP!$/')
        ->and($commit->method)->toBe('POST')
        ->and($commit->url)->toBe('https://dev-null.test/api/cli/commits')
        ->and($commit->body)->toBe($signer->signed[0]['message'])
        ->and($commit->header('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($commit->header('X-SSH-Signature'))->toBe(base64_encode('devnull-commit:/home/me/.ssh/id_rsa'));
});

it('replies to a commit', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/cli/commits', 'https://dev-null.test/@me/commits/def', 201);
    $signer = new FakeSigner();

    client($transport, $signer, key: '/home/me/.ssh/id_ed25519')->commit('Agreed!', '01a1680b');

    expect($signer->signed[0]['message'])->toMatch('/^\d{10}\n[0-9a-f]{32}\n01a1680b\nAgreed!$/');
});

it('signs with the configured key without looking keys up', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/cli/commits', 'link', 201);
    $signer = new FakeSigner();

    client($transport, $signer, new FakeKeyRing(['ssh-rsa BBBB' => '/home/me/.ssh/id_rsa']), '/keys/devnull')->commit('Hi', '');

    expect($transport->requests)->toHaveCount(1)
        ->and($signer->signed[0]['key'])->toBe('/keys/devnull');
});

it('needs a local key', function (): void {
    client(new FakeTransport())->commit('Hi', '');
})->throws(RuntimeException::class, 'No Ed25519 or RSA SSH key found in your SSH agent or in ~/.ssh.');

it('needs a key on the GitHub account of a /dev/null user', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/cli/key-lookups', '');

    client($transport, keys: new FakeKeyRing(['ssh-rsa BBBB' => '/home/me/.ssh/id_rsa']))->commit('Hi', '');
})->throws(RuntimeException::class, 'None of your SSH keys is on the GitHub account of a /dev/null user.');

it('reports rejected commits', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/cli/commits', ['message' => 'The signature has expired.'], 422);

    client($transport, key: '/keys/devnull')->commit('Hi', '');
})->throws(ApiException::class, 'devnull: POST /api/cli/commits failed (HTTP 422): The signature has expired.');
