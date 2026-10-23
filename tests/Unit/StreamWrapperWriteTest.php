<?php

declare(strict_types=1);

use Jubianchi\DevNull\StreamWrapper;
use Jubianchi\DevNull\Tests\Fakes\FakeSigner;

afterEach(function (): void {
    StreamWrapper::useClientFactory(null);
});

it('commits what is written to devnull://@me', function (): void {
    $signer = new FakeSigner();
    $api = fakeApi($signer, key: '/keys/devnull')->on('POST', '/api/cli/commits', "https://dev-null.test/@me/commits/abc\n", 201);

    expect(file_put_contents('devnull://@me', 'Hello from PHP!'))->toBe(15)
        ->and(StreamWrapper::$lastResponse)->toBe('https://dev-null.test/@me/commits/abc')
        ->and($api->requests)->toHaveCount(1)
        ->and($signer->signed[0]['message'])->toEndWith("\n\nHello from PHP!");
});

it('sends several writes as a single commit, when the stream closes', function (): void {
    $api = fakeApi(key: '/keys/devnull')->on('POST', '/api/cli/commits', 'link', 201);

    $stream = fopen('devnull://@me', 'w') ?: throw new RuntimeException('Not opened.');
    fwrite($stream, "Hello\n");
    fwrite($stream, 'world');
    $before = count($api->requests);
    fclose($stream);

    expect($before)->toBe(0)
        ->and($api->requests)->toHaveCount(1)
        ->and($api->last()->body)->toEndWith("\n\nHello\nworld");
});

it('replies to what is written to a commit', function (): void {
    $signer = new FakeSigner();
    fakeApi($signer, key: '/keys/devnull')->on('POST', '/api/cli/commits', 'https://dev-null.test/@me/commits/def', 201);

    file_put_contents('devnull://@dseguy/commits/01a1680b', "Let's do it!");

    expect($signer->signed[0]['message'])->toEndWith("\n01a1680b\nLet's do it!")
        ->and(StreamWrapper::$lastResponse)->toBe('https://dev-null.test/@me/commits/def');
});

it('sends nothing when nothing but blanks was written', function (): void {
    $api = fakeApi(key: '/keys/devnull');

    file_put_contents('devnull://@me', " \n ");

    expect($api->requests)->toBe([]);
});

it('explains where to write', function (string $url, string $warning): void {
    fakeApi(key: '/keys/devnull');

    [$result, $warnings] = withWarnings(fn(): int|false => file_put_contents($url, 'Hi'));

    expect($result)->toBeFalse()
        ->and($warnings)->toContain($warning);
})->with([
    'replies' => ['devnull://@me/replies', 'Write to devnull://@you to commit, or to devnull://@user/commits/<id> to reply, not to devnull://@me/replies.'],
    'a star' => ['devnull://@me/commits/abc/star', 'Write to devnull://@you to commit, or to devnull://@user/commits/<id> to reply, not to devnull://@me/commits/abc/star.'],
    'no user' => ['devnull://me', 'Expected devnull://@username, got devnull://me.'],
]);

it('warns and forgets the last response when the commit fails', function (): void {
    fakeApi(key: '/keys/devnull')
        ->on('POST', '/api/cli/commits', 'link', 201);
    file_put_contents('devnull://@me', 'First');

    fakeApi(key: '/keys/devnull')->on('POST', '/api/cli/commits', ['message' => 'Too many commits.'], 429);
    [, $warnings] = withWarnings(fn(): int|false => file_put_contents('devnull://@me', 'Second'));

    expect(StreamWrapper::$lastResponse)->toBe('')
        ->and($warnings)->toContain('devnull: POST /api/cli/commits failed (HTTP 429): Too many commits.');
});
