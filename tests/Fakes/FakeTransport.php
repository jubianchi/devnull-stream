<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Tests\Fakes;

use Jubianchi\DevNull\Http\Request;
use Jubianchi\DevNull\Http\Response;
use Jubianchi\DevNull\Http\Transport;

final class FakeTransport implements Transport
{
    /** @var list<Request> */
    public private(set) array $requests = [];

    /** @var array<string, Response> */
    private array $routes = [];

    /**
     * @param array<mixed>|string $body
     */
    public function on(string $method, string $uri, array|string $body, int $status = 200): self
    {
        $this->routes["{$method} {$uri}"] = new Response(
            $status,
            is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
        );

        return $this;
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;
        $uri = (string) preg_replace('#^https?://[^/]+#', '', $request->url);

        return $this->routes["{$request->method} {$uri}"] ?? new Response(404, '{"message":"Not found."}');
    }

    public function last(): Request
    {
        return $this->requests[array_key_last($this->requests) ?? throw new \LogicException('No request was sent.')];
    }
}
