<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Http;

final readonly class Request
{
    /**
     * @param list<string> $headers
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public string $body = '',
    ) {}

    public function header(string $name): ?string
    {
        foreach ($this->headers as $header) {
            [$key, $value] = explode(':', $header, 2) + [1 => ''];

            if (strcasecmp(trim($key), $name) === 0) {
                return trim($value);
            }
        }

        return null;
    }
}
