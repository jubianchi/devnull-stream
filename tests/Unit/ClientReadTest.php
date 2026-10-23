<?php

declare(strict_types=1);

use Jubianchi\DevNull\ApiException;
use Jubianchi\DevNull\Client;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

it('gets JSON from the API', function (): void {
    $transport = new FakeTransport()->on('GET', '/api/v1/users/dseguy', ['data' => ['username' => 'dseguy']]);

    expect(client($transport)->get('/api/v1/users/dseguy'))->toBe(['data' => ['username' => 'dseguy']]);

    $request = $transport->last();
    expect($request->method)->toBe('GET')
        ->and($request->url)->toBe('https://dev-null.test/api/v1/users/dseguy')
        ->and($request->headers)->toContain('Accept: application/json', 'User-Agent: devnull-php-stream')
        ->and($request->body)->toBe('');
});

it('reads empty responses as an empty array', function (): void {
    expect(client(new FakeTransport()->on('GET', '/empty', ''))->get('/empty'))->toBe([]);
});

it('reports API errors', function (array|string $body, string $message): void {
    expect(fn() => client(new FakeTransport()->on('GET', '/api/v1/commits/nope', $body, 404))->get('/api/v1/commits/nope'))
        ->toThrow(ApiException::class, $message);
})->with([
    'message' => [['message' => 'No query results.'], 'No query results.'],
    'nested message' => [['error' => ['message' => 'Too many requests.']], 'Too many requests.'],
    'plain text' => ["  Oops\n", '(HTTP 404): Oops'],
]);

it('puts the method, the URI, the status and the message in the error', function (): void {
    expect(fn() => client(new FakeTransport()->on('GET', '/x', ['message' => 'Nope.'], 403))->get('/x'))
        ->toThrow(ApiException::class, 'devnull: GET /x failed (HTTP 403): Nope.');
});

it('follows the cursors of paginated lists', function (): void {
    $transport = new FakeTransport()
        ->on('GET', '/api/v1/users/dseguy/commits?replies=1', ['data' => [commit('a'), commit('b')], 'meta' => ['next_cursor' => 'c/1']])
        ->on('GET', '/api/v1/users/dseguy/commits?replies=1&cursor=c%2F1', ['data' => [commit('c')], 'meta' => ['next_cursor' => 'c2']])
        ->on('GET', '/api/v1/users/dseguy/commits?replies=1&cursor=c2', ['data' => [commit('d')], 'meta' => ['next_cursor' => null]]);

    $ids = [];
    foreach (client($transport)->pages('/api/v1/users/dseguy/commits?replies=1') as $page) {
        $ids[] = array_column($page, 'id');
    }

    expect($ids)->toBe([['a', 'b'], ['c'], ['d']]);
});

it('fetches pages lazily', function (): void {
    $transport = new FakeTransport()
        ->on('GET', '/list', ['data' => [commit('a')], 'meta' => ['next_cursor' => 'n']])
        ->on('GET', '/list?cursor=n', ['data' => [commit('b')]]);

    $pages = client($transport)->pages('/list');
    $pages->current();

    expect($transport->requests)->toHaveCount(1);
});

it('refuses URLs that are not https', function (string $url): void {
    new Client($url, new FakeTransport());
})->with([
    'http://dev-null.sh',
    'ftp://dev-null.sh',
    'http://localhost.evil.com',
])->throws(InvalidArgumentException::class, 'it is not an https:// URL.');

it('accepts local http URLs', function (string $url): void {
    expect(new Client($url, new FakeTransport()))->toBeInstanceOf(Client::class);
})->with([
    'http://localhost',
    'http://127.0.0.1:8000',
]);
