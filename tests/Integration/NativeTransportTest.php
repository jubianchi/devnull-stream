<?php

declare(strict_types=1);

use Jubianchi\DevNull\Http\NativeTransport;
use Jubianchi\DevNull\Http\Request;
use Jubianchi\DevNull\Tests\Support\EchoServer;

beforeAll(function (): void {
    EchoServer::start();
});

afterAll(function (): void {
    EchoServer::stop();
});

it('sends the method, the headers and the body', function (): void {
    $response = new NativeTransport()->send(new Request(
        'POST',
        EchoServer::url('/api/cli/commits?x=1'),
        ['Content-Type: text/plain; charset=utf-8', 'X-SSH-Signature: c2ln'],
        "hello\nworld",
    ));

    $echo = EchoServer::decode($response->body);

    expect($response->status)->toBe(200)
        ->and($echo['method'])->toBe('POST')
        ->and($echo['uri'])->toBe('/api/cli/commits?x=1')
        ->and($echo['headers'])->toMatchArray(['Content-Type' => 'text/plain; charset=utf-8', 'X-SSH-Signature' => 'c2ln'])
        ->and($echo['body'])->toBe("hello\nworld");
});

it('returns error responses instead of throwing', function (): void {
    $response = new NativeTransport()->send(new Request('DELETE', EchoServer::url('/?status=422')));

    expect($response->status)->toBe(422)
        ->and(EchoServer::decode($response->body)['method'])->toBe('DELETE');
});

it('does not follow redirects', function (): void {
    expect(new NativeTransport()->send(new Request('GET', EchoServer::url('/?redirect=1')))->status)->toBe(302);
});

it('reports unreachable hosts as status 0, with the reason as body', function (): void {
    $response = new NativeTransport()->send(new Request('GET', 'http://127.0.0.1:1/'));

    expect($response->status)->toBe(0)
        ->and($response->body)->toContain('Connection refused');
});
