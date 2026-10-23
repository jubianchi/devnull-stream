<?php

declare(strict_types=1);

use Jubianchi\DevNull\Client;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

const TEST_URL = 'https://dev-null.test';

function client(FakeTransport $transport): Client
{
    return new Client(TEST_URL, $transport);
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
