<?php

declare(strict_types=1);

use Jubianchi\DevNull\Keys\PublicKey;
use Jubianchi\DevNull\Signing\SshKeygenSigner;
use Jubianchi\DevNull\Tests\Support\SshKeys;
use PHPUnit\Framework\Assert;

beforeEach(function (): void {
    if (!SshKeys::available()) {
        Assert::markTestSkipped('ssh-keygen is not installed.');
    }
});

it('signs with a private key file, as dev-null.sh verifies', function (): void {
    $directory = SshKeys::directory();

    try {
        $key = SshKeys::generate($directory);
        $message = "1760000000\n" . str_repeat('0', 32) . "\n\nHello from PHP!";

        $signature = new SshKeygenSigner()->sign($message, 'devnull-commit', $key);

        expect($signature)->toMatch('#^[A-Za-z0-9+/]+=*$#')
            ->and(SshKeys::verify(SshKeys::publicKey($key), 'devnull-commit', $message, $signature))->toBeTrue()
            ->and(SshKeys::verify(SshKeys::publicKey($key), 'devnull-api', $message, $signature))->toBeFalse()
            ->and(SshKeys::verify(SshKeys::publicKey($key), 'devnull-commit', "{$message}!", $signature))->toBeFalse();
    } finally {
        SshKeys::remove($directory);
    }
});

it('signs through the agent with a public key', function (): void {
    $directory = SshKeys::directory();
    $agent = null;

    try {
        $key = SshKeys::generate($directory);
        $agent = SshKeys::startAgent($key) ?? throw new RuntimeException('No ssh-agent.');
        $publicKey = PublicKey::id(SshKeys::publicKey($key));
        unlink($key);

        $signature = new SshKeygenSigner()->sign('message', 'devnull-api', $publicKey);

        expect(SshKeys::verify($publicKey, 'devnull-api', 'message', $signature))->toBeTrue();
    } finally {
        if ($agent !== null) {
            SshKeys::stopAgent($agent);
        }

        SshKeys::remove($directory);
    }
});

it('fingerprints keys as ssh-keygen does', function (): void {
    $directory = SshKeys::directory();

    try {
        $key = SshKeys::generate($directory);
        exec('ssh-keygen -l -E sha256 -f ' . escapeshellarg("{$key}.pub"), $output);

        expect(explode(' ', $output[0] ?? '')[1] ?? null)->toBe(PublicKey::fingerprint(SshKeys::publicKey($key)));
    } finally {
        SshKeys::remove($directory);
    }
});

it('explains why it could not sign', function (): void {
    new SshKeygenSigner()->sign('message', 'devnull-api', 'ssh-ed25519 AAAAnotakey');
})->throws(RuntimeException::class, 'Could not sign with ssh-ed25519 AAAAnotakey:');
