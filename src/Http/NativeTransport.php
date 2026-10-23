<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Http;

/**
 * PHP's own http(s) stream: no extension or library needed, only allow_url_fopen.
 */
final readonly class NativeTransport implements Transport
{
    public function __construct(
        private float $timeout = 30,
    ) {}

    public function send(Request $request): Response
    {
        $context = stream_context_create(['http' => [
            'method' => $request->method,
            'header' => $request->headers,
            'content' => $request->body,
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => $this->timeout,
        ]]);

        $error = '';
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            http_clear_last_response_headers();
            $body = file_get_contents($request->url, false, $context);
            $status = preg_match('#^HTTP/\S+ (\d{3})#', http_get_last_response_headers()[0] ?? '', $match) === 1 ? (int) $match[1] : 0;
        } finally {
            restore_error_handler();
        }

        return new Response($status, $body === false ? $error : $body);
    }
}
