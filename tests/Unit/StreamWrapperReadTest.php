<?php

declare(strict_types=1);

use Jubianchi\DevNull\Client;
use Jubianchi\DevNull\CommitFormatter;
use Jubianchi\DevNull\Settings;
use Jubianchi\DevNull\StreamWrapper;

afterEach(function (): void {
    StreamWrapper::useClientFactory(null);
});

it("reads a user's commits, page after page", function (): void {
    fakeApi()
        ->on('GET', '/api/v1/users/dseguy/commits', ['data' => [commit('a'), commit('b')], 'meta' => ['next_cursor' => 'n']])
        ->on('GET', '/api/v1/users/dseguy/commits?cursor=n', ['data' => [commit('c')], 'meta' => ['next_cursor' => null]]);

    expect(file_get_contents('devnull://@dseguy'))
        ->toBe(CommitFormatter::format(commit('a')) . CommitFormatter::format(commit('b')) . CommitFormatter::format(commit('c')));
});

it("reads a user's replies", function (): void {
    fakeApi()->on('GET', '/api/v1/users/dseguy/commits?replies=1', ['data' => [commit('r', ['parent_id' => 'a'])]]);

    expect(file_get_contents('devnull://@dseguy/replies'))->toBe(CommitFormatter::format(commit('r', ['parent_id' => 'a'])));
});

it('reads one commit', function (): void {
    fakeApi()->on('GET', '/api/v1/commits/abc', ['data' => commit('abc')]);

    expect(file_get_contents('devnull://@dseguy/commits/abc'))->toBe(CommitFormatter::format(commit('abc')));
});

it('fetches the next page only when the reader needs it', function (): void {
    $api = fakeApi()
        ->on('GET', '/api/v1/users/dseguy/commits', ['data' => [commit('a', ['body' => str_repeat('a', 10_000)])], 'meta' => ['next_cursor' => 'n']])
        ->on('GET', '/api/v1/users/dseguy/commits?cursor=n', ['data' => [commit('b')]]);

    $stream = fopen('devnull://@dseguy', 'r') ?: throw new RuntimeException('Not opened.');
    $line = fgets($stream);
    $requests = count($api->requests);
    $rest = stream_get_contents($stream);
    fclose($stream);

    expect($line)->toBe("commit a\n")
        ->and($requests)->toBe(1)
        ->and($rest)->toEndWith("commit b\n" . substr(CommitFormatter::format(commit('b')), 9))
        ->and($api->requests)->toHaveCount(2);
});

it('warns about paths with nothing to read', function (string $url, string $warning): void {
    fakeApi();

    [$result, $warnings] = withWarnings(fn(): string|false => file_get_contents($url));

    expect($result)->toBeFalse()
        ->and($warnings)->toContain($warning);
})->with([
    'a star' => ['devnull://@dseguy/commits/abc/star', 'Nothing to read at devnull://@dseguy/commits/abc/star.'],
    'no user' => ['devnull://dseguy', 'Expected devnull://@username, got devnull://dseguy.'],
]);

it('warns when a commit cannot be read', function (): void {
    fakeApi();

    [$result, $warnings] = withWarnings(fn(): string|false => file_get_contents('devnull://@dseguy/commits/nope'));

    expect($result)->toBeFalse()
        ->and($warnings)->toContain('devnull: GET /api/v1/commits/nope failed (HTTP 404): Not found.');
});

it('keeps what it read when a later page fails', function (): void {
    fakeApi()
        ->on('GET', '/api/v1/users/dseguy/commits', ['data' => [commit('a')], 'meta' => ['next_cursor' => 'n']])
        ->on('GET', '/api/v1/users/dseguy/commits?cursor=n', ['message' => 'Too many requests.'], 429);

    [$result, $warnings] = withWarnings(fn(): string|false => file_get_contents('devnull://@dseguy'));

    expect($result)->toBe(CommitFormatter::format(commit('a')))
        ->and($warnings)->toContain('devnull: GET /api/v1/users/dseguy/commits?cursor=n failed (HTTP 429): Too many requests.');
});

it("lists a user's commit ids", function (): void {
    fakeApi()
        ->on('GET', '/api/v1/users/dseguy/commits', ['data' => [commit('a'), commit('b')], 'meta' => ['next_cursor' => 'n']])
        ->on('GET', '/api/v1/users/dseguy/commits?cursor=n', ['data' => [commit('c')]]);

    expect(scandir('devnull://@dseguy', SCANDIR_SORT_NONE))->toBe(['a', 'b', 'c']);
});

it("lists a user's reply ids", function (): void {
    fakeApi()->on('GET', '/api/v1/users/dseguy/commits?replies=1', ['data' => [commit('r')]]);

    expect(scandir('devnull://@dseguy/replies'))->toBe(['r']);
});

it('refuses to list a commit', function (): void {
    fakeApi();

    [$result, $warnings] = withWarnings(fn(): array|false => scandir('devnull://@dseguy/commits/abc'));

    expect($result)->toBeFalse()
        ->and($warnings)->toContain('Not a directory: devnull://@dseguy/commits/abc');
});

it('stats users and their replies as directories', function (string $url): void {
    fakeApi()->on('GET', '/api/v1/users/dseguy', ['data' => ['username' => 'dseguy', 'created_at' => '2026-01-02T03:04:05Z']]);

    expect(is_dir($url))->toBeTrue()
        ->and(is_file($url))->toBeFalse()
        ->and(filemtime($url))->toBe(strtotime('2026-01-02T03:04:05Z'));
})->with(['devnull://@dseguy', 'devnull://@dseguy/replies']);

it('stats commits as files the size of their text', function (): void {
    fakeApi()->on('GET', '/api/v1/commits/abc', ['data' => commit('abc')]);

    expect(file_exists('devnull://@dseguy/commits/abc'))->toBeTrue()
        ->and(is_file('devnull://@dseguy/commits/abc'))->toBeTrue()
        ->and(filesize('devnull://@dseguy/commits/abc'))->toBe(strlen(CommitFormatter::format(commit('abc'))))
        ->and(stat('devnull://@dseguy/commits/abc'))->toMatchArray(['mode' => 0100644, 'mtime' => strtotime('2026-10-18T10:00:00Z')]);
});

it('finds no missing commit, quietly', function (): void {
    fakeApi();

    [$result, $warnings] = withWarnings(fn(): bool => file_exists('devnull://@dseguy/commits/nope'));

    expect($result)->toBeFalse()
        ->and($warnings)->toBe([]);
});

it('warns when stat() finds nothing', function (): void {
    fakeApi();

    [$result, $warnings] = withWarnings(fn(): array|false => stat('devnull://@dseguy/commits/abc/star'));

    expect($result)->toBeFalse()
        ->and($warnings)->toContain('No such commit or user: devnull://@dseguy/commits/abc/star');
});

it('builds its client from the stream context options', function (): void {
    $api = fakeApi()->on('GET', '/api/v1/commits/abc', ['data' => commit('abc')]);
    $seen = [];
    StreamWrapper::useClientFactory(static function (Settings $settings) use (&$seen, $api): Client {
        $seen[] = $settings;

        return client($api);
    });

    file_get_contents('devnull://@dseguy/commits/abc', false, stream_context_create([
        'devnull' => ['url' => 'http://localhost:8000', 'key' => 'ssh-ed25519 AAAA'],
    ]));

    expect($seen)->toEqual([new Settings('http://localhost:8000', 'ssh-ed25519 AAAA')]);
});
