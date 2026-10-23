<?php

declare(strict_types=1);

use Jubianchi\DevNull\StreamWrapper;

afterEach(function (): void {
    StreamWrapper::useClientFactory(null);
});

it('stars a commit and gives it pasteis with touch()', function (string $url, string $uri): void {
    $api = fakeApi(key: '/keys/device')->on('POST', $uri, ['data' => []]);

    expect(touch($url))->toBeTrue()
        ->and($api->last()->method)->toBe('POST')
        ->and($api->last()->url)->toBe(TEST_URL . $uri)
        ->and($api->last()->header('X-SSH-Signature'))->not->toBeNull();
})->with([
    'star' => ['devnull://@dseguy/commits/abc/star', '/api/v1/commits/abc/star'],
    'pasteis' => ['devnull://@dseguy/commits/abc/pasteis', '/api/v1/commits/abc/pasteis'],
]);

it('unstars a commit and deletes one with unlink()', function (string $url, string $uri): void {
    $api = fakeApi(key: '/keys/device')->on('DELETE', $uri, '', 204);

    expect(unlink($url))->toBeTrue()
        ->and($api->last()->method)->toBe('DELETE')
        ->and($api->last()->url)->toBe(TEST_URL . $uri);
})->with([
    'star' => ['devnull://@dseguy/commits/abc/star', '/api/v1/commits/abc/star'],
    'commit' => ['devnull://@me/commits/abc', '/api/v1/commits/abc'],
]);

it('only touches stars and pasteis', function (string $url): void {
    $api = fakeApi(key: '/keys/device');

    [$result, $warnings] = withWarnings(fn(): bool => touch($url));

    expect($result)->toBeFalse()
        ->and($warnings)->toContain("touch() a commit's star or pasteis, not {$url}.")
        ->and($api->requests)->toBe([]);
})->with(['devnull://@dseguy', 'devnull://@dseguy/commits/abc', 'devnull://@dseguy/commits/abc/other']);

it('only unlinks commits and stars', function (string $url): void {
    $api = fakeApi(key: '/keys/device');

    [$result, $warnings] = withWarnings(fn(): bool => unlink($url));

    expect($result)->toBeFalse()
        ->and($warnings)->toContain("unlink() a commit or its star, not {$url}.")
        ->and($api->requests)->toBe([]);
})->with(['devnull://@dseguy', 'devnull://@dseguy/replies', 'devnull://@dseguy/commits/abc/pasteis']);

it('ignores the other metadata changes', function (): void {
    $api = fakeApi(key: '/keys/device');

    expect(@chmod('devnull://@dseguy/commits/abc/star', 0644))->toBeFalse()
        ->and($api->requests)->toBe([]);
});

it('warns when the signed API refuses', function (): void {
    fakeApi(key: '/keys/device')->on('POST', '/api/v1/commits/abc/star', ['message' => "This key isn't approved."], 401);

    [$result, $warnings] = withWarnings(fn(): bool => touch('devnull://@dseguy/commits/abc/star'));

    expect($result)->toBeFalse()
        ->and($warnings)->toContain("devnull: POST /api/v1/commits/abc/star failed (HTTP 401): This key isn't approved.");
});
