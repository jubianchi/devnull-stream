<?php

declare(strict_types=1);

use Jubianchi\DevNull\Authorization\AuthorizationFlow;
use Jubianchi\DevNull\Authorization\FallbackFlow;
use Jubianchi\DevNull\Authorization\FlowUnavailable;
use Jubianchi\DevNull\Authorization\Pkce;
use Jubianchi\DevNull\Authorization\PkceFlow;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

/**
 * @param array<mixed>|null $answer null for a flow the server doesn't offer
 */
function flow(?array $answer, int &$calls = 0): AuthorizationFlow
{
    return new class ($answer, $calls) implements AuthorizationFlow {
        /**
         * @param array<mixed>|null $answer
         */
        public function __construct(
            private readonly ?array $answer,
            private int &$calls,
        ) {}

        public function authorize(string $deviceName): array
        {
            $this->calls++;

            return $this->answer ?? throw new FlowUnavailable('Not here.');
        }
    };
}

it('uses the first flow the server offers', function (): void {
    $second = 0;
    $third = 0;

    $me = new FallbackFlow(flow(null), flow(['data' => ['username' => 'me']], $second), flow(['data' => []], $third))->authorize('PHP');

    expect($me)->toBe(['data' => ['username' => 'me']])
        ->and($second)->toBe(1)
        ->and($third)->toBe(0);
});

it('is unavailable when no flow is', function (): void {
    new FallbackFlow(flow(null), flow(null))->authorize('PHP');
})->throws(FlowUnavailable::class);

it('approves with PKCE through the client', function (): void {
    $transport = new FakeTransport()->on('POST', '/api/v1/devices', ['data' => ['username' => 'me']], 201);
    $asked = [];

    $me = new PkceFlow(
        client($transport, key: 'ssh-ed25519 AAAA'),
        static function (string $url) use (&$asked): string {
            $asked[] = $url;

            return 'devnull://authorize?code=c&state=s';
        },
        static fn(): Pkce => new Pkce(str_repeat('v', 43), 's'),
    )->authorize('My laptop');

    expect($me)->toBe(['data' => ['username' => 'me']])
        ->and($asked[0] ?? '')->toContain('device_name=My+laptop')
        ->and($transport->last()->body)->toBe('{"code":"c","code_verifier":"' . str_repeat('v', 43) . '"}');
});
