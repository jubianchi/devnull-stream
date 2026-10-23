<?php

declare(strict_types=1);

namespace Jubianchi\DevNull;

final readonly class Settings
{
    public const string DEFAULT_URL = 'https://dev-null.sh';

    public function __construct(
        public string $url,
        public ?string $key,
    ) {}

    /**
     * The `devnull` stream context options win over DEVNULL_URL and DEVNULL_KEY.
     *
     * @param array<mixed> $options
     */
    public static function resolve(array $options = []): self
    {
        $url = self::nonEmpty($options['url'] ?? null) ?? self::nonEmpty(getenv('DEVNULL_URL')) ?? self::DEFAULT_URL;

        return new self(
            rtrim($url, '/'),
            self::nonEmpty($options['key'] ?? null) ?? self::nonEmpty(getenv('DEVNULL_KEY')),
        );
    }

    private static function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
