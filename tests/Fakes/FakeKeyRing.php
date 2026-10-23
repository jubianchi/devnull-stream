<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Tests\Fakes;

use Jubianchi\DevNull\Keys\KeyRing;

final readonly class FakeKeyRing implements KeyRing
{
    /**
     * @param array<string, string> $keys
     */
    public function __construct(
        private array $keys = [],
    ) {}

    public function keys(): array
    {
        return $this->keys;
    }

    public function publicKeyOf(string $key): string
    {
        return array_search($key, $this->keys, true) ?: $key;
    }
}
