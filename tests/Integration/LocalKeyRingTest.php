<?php

declare(strict_types=1);

use Jubianchi\DevNull\Keys\LocalKeyRing;
use Jubianchi\DevNull\Keys\PublicKey;
use Jubianchi\DevNull\Tests\Support\SshKeys;
use PHPUnit\Framework\Assert;

beforeEach(function (): void {
    if (!SshKeys::available()) {
        Assert::markTestSkipped('ssh-keygen is not installed.');
    }

    putenv('SSH_AUTH_SOCK=/nonexistent/agent.sock');
});

afterEach(function (): void {
    putenv('SSH_AUTH_SOCK');
});

it('finds the Ed25519 and RSA keys of ~/.ssh that have a private half', function (): void {
    $home = SshKeys::directory();

    try {
        mkdir("{$home}/.ssh");
        $ed25519 = SshKeys::generate("{$home}/.ssh", 'id_ed25519');
        $rsa = SshKeys::generate("{$home}/.ssh", 'id_rsa', 'rsa');
        SshKeys::generate("{$home}/.ssh", 'id_ecdsa', 'ecdsa');
        unlink(SshKeys::generate("{$home}/.ssh", 'orphan'));

        $keys = new LocalKeyRing($home)->keys();
        ksort($keys);
        $expected = [
            PublicKey::id(SshKeys::publicKey($ed25519)) => $ed25519,
            PublicKey::id(SshKeys::publicKey($rsa)) => $rsa,
        ];
        ksort($expected);

        expect($keys)->toBe($expected);
    } finally {
        foreach (glob("{$home}/.ssh/*") ?: [] as $file) {
            unlink($file);
        }

        rmdir("{$home}/.ssh");
        SshKeys::remove($home);
    }
});

it("finds the agent's keys, by their public half", function (): void {
    $home = SshKeys::directory();
    $agent = null;

    try {
        $key = SshKeys::generate($home);
        $agent = SshKeys::startAgent($key) ?? throw new RuntimeException('No ssh-agent.');
        $id = PublicKey::id(SshKeys::publicKey($key));

        expect(new LocalKeyRing($home)->keys())->toBe([$id => $id]);
    } finally {
        if ($agent !== null) {
            SshKeys::stopAgent($agent);
        }

        SshKeys::remove($home);
    }
});

it('finds nothing without an agent or ~/.ssh', function (): void {
    $home = SshKeys::directory();

    try {
        expect(new LocalKeyRing($home)->keys())->toBe([]);
    } finally {
        SshKeys::remove($home);
    }
});

it('reads the public half of a key', function (): void {
    $home = SshKeys::directory();

    try {
        $key = SshKeys::generate($home);
        $id = PublicKey::id(SshKeys::publicKey($key));

        expect(new LocalKeyRing($home)->publicKeyOf($key))->toBe($id)
            ->and(new LocalKeyRing($home)->publicKeyOf($id))->toBe($id);
    } finally {
        SshKeys::remove($home);
    }
});
