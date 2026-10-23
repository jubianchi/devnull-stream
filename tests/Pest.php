<?php

declare(strict_types=1);

use Jubianchi\DevNull\Client;
use Jubianchi\DevNull\Keys\KeyRing;
use Jubianchi\DevNull\Signing\Signer;
use Jubianchi\DevNull\StreamWrapper;
use Jubianchi\DevNull\Tests\Fakes\FakeKeyRing;
use Jubianchi\DevNull\Tests\Fakes\FakeSigner;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

const TEST_URL = 'https://dev-null.test';

function client(FakeTransport $transport, ?Signer $signer = null, ?KeyRing $keys = null, ?string $key = null): Client
{
    return new Client(TEST_URL, $transport, $signer ?? new FakeSigner(), $keys ?? new FakeKeyRing(), $key);
}

/**
 * Points the stream wrapper at a fake API.
 */
function fakeApi(?Signer $signer = null, ?KeyRing $keys = null, ?string $key = null): FakeTransport
{
    $transport = new FakeTransport();
    StreamWrapper::register();
    StreamWrapper::useClientFactory(static fn(): Client => client($transport, $signer, $keys, $key));

    return $transport;
}

/**
 * Runs the callback and returns its result with the messages of the warnings it raised.
 *
 * @template T
 * @param Closure(): T $callback
 * @return array{T, list<string>}
 */
function withWarnings(Closure $callback): array
{
    $warnings = [];
    set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    }, E_WARNING | E_USER_WARNING);

    try {
        return [$callback(), $warnings];
    } finally {
        restore_error_handler();
    }
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function commit(string $id, array $overrides = []): array
{
    return [
        'id' => $id,
        'parent_id' => null,
        'author' => ['name' => 'Damien Seguy', 'username' => 'dseguy'],
        'created_at' => '2026-10-18T10:00:00Z',
        'stars_count' => 0,
        'replies_count' => 0,
        'body' => "Body of {$id}",
        ...$overrides,
    ];
}
