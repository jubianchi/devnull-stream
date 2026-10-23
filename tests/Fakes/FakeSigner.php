<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Tests\Fakes;

use Jubianchi\DevNull\Signing\Signer;

final class FakeSigner implements Signer
{
    /** @var list<array{message: string, namespace: string, key: string}> */
    public private(set) array $signed = [];

    public function sign(string $message, string $namespace, string $key): string
    {
        $this->signed[] = ['message' => $message, 'namespace' => $namespace, 'key' => $key];

        return base64_encode("{$namespace}:{$key}");
    }
}
