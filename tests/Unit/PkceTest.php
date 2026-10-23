<?php

declare(strict_types=1);

use Jubianchi\DevNull\Authorization\Pkce;

it('derives the S256 challenge: unpadded base64url of the SHA-256 of the verifier', function (): void {
    // printf %s <verifier> | openssl dgst -sha256 -binary | base64 | tr '+/' '-_' | tr -d '='
    expect(new Pkce('dBjftJeZ4CVP-mJ92K1s3N8r3UHB0WbZg3tAyq3z6YI', 'state')->challenge())
        ->toBe('6LiCYNASrOLLDspnWP33XV0xvYwBiWECHDYC2fqDESk');
});

it('generates verifiers and states dev-null.sh accepts', function (): void {
    $pkce = Pkce::generate();

    expect($pkce->verifier)->toMatch('/^[A-Za-z0-9._~-]{43,128}$/')
        ->and($pkce->challenge())->toMatch('/^[A-Za-z0-9_-]{43}$/')
        ->and($pkce->state)->toMatch('/^[0-9a-f]{32}$/')
        ->and(Pkce::generate()->verifier)->not->toBe($pkce->verifier);
});
