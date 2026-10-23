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

    /** @var array<string, list<Response>> */
    private array $routes = [];

    /**
     * Answers several times in the order they were given, then keeps repeating the last answer.
     *
     * @param array<mixed>|string $body
     */
    public function on(string $method, string $uri, array|string $body, int $status = 200): self
    {
        $this->routes["{$method} {$uri}"][] = new Response(
            $status,
            is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
        );

        return $this;
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;
        $route = "{$request->method} " . preg_replace('#^https?://[^/]+#', '', $request->url);

        $answers = $this->routes[$route] ?? [];

        if ($answers === []) {
            return new Response(404, '{"message":"Not found."}');
        }

        if (count($answers) > 1) {
            array_shift($this->routes[$route]);
        }

        return $answers[0];
    }

    public function last(): Request
    {
        return $this->requests[array_key_last($this->requests) ?? throw new \LogicException('No request was sent.')];
    }
}
