<?php

declare(strict_types=1);

use Jubianchi\DevNull\Keys\PublicKey;

it('keeps the type and the base64 of a public key line', function (string $line): void {
    expect(PublicKey::id($line))->toBe('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIA');
})->with([
    'bare' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIA',
    'with a comment' => "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIA me@laptop\n",
    'with spaces' => "  ssh-ed25519\tAAAAC3NzaC1lZDI1NTE5AAAAIA  me  ",
    'first line only' => "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIA\nssh-rsa AAAAB3",
]);

it('reads nothing from an empty line', function (): void {
    expect(PublicKey::id(''))->toBe('');
});

it('fingerprints a public key as ssh-keygen -l does', function (): void {
    $blob = 'AAAAC3NzaC1lZDI1NTE5AAAAIA';

    expect(PublicKey::fingerprint("ssh-ed25519 {$blob}"))
        ->toBe('SHA256:' . rtrim(base64_encode(hash('sha256', (string) base64_decode($blob), true)), '='));
});

it('tells the key types dev-null.sh accepts', function (string $id, bool $supported): void {
    expect(PublicKey::isSupported($id))->toBe($supported);
})->with([
    ['ssh-ed25519 AAAA', true],
    ['ssh-rsa AAAA', true],
    ['ecdsa-sha2-nistp256 AAAA', false],
    ['sk-ssh-ed25519@openssh.com AAAA', false],
]);
