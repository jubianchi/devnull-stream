<?php

declare(strict_types=1);

use Jubianchi\DevNull\CommitAction;
use Jubianchi\DevNull\Path;

it('parses a user', function (): void {
    $path = Path::parse('devnull://@dseguy');

    expect($path->user)->toBe('dseguy')
        ->and($path->tail)->toBe([])
        ->and($path->listingUri())->toBe('/api/v1/users/dseguy/commits')
        ->and($path->commitId())->toBeNull()
        ->and($path->isCommitTarget())->toBeTrue();
});

it("parses a user's replies", function (): void {
    $path = Path::parse('devnull://@dseguy/replies/');

    expect($path->tail)->toBe(['replies'])
        ->and($path->listingUri())->toBe('/api/v1/users/dseguy/commits?replies=1')
        ->and($path->isCommitTarget())->toBeFalse();
});

it('parses a commit', function (): void {
    $path = Path::parse('devnull://@dseguy//commits/01a1680b-43d8');

    expect($path->tail)->toBe(['commits', '01a1680b-43d8'])
        ->and($path->listingUri())->toBeNull()
        ->and($path->commitId())->toBe('01a1680b-43d8')
        ->and($path->commitAction())->toBeNull()
        ->and($path->isCommitTarget())->toBeTrue();
});

it("parses a commit's star and pasteis", function (string $segment, CommitAction $action): void {
    $path = Path::parse("devnull://@dseguy/commits/abc/{$segment}");

    expect($path->commitId())->toBeNull()
        ->and($path->commitAction())->toBe(['abc', $action])
        ->and($path->isCommitTarget())->toBeFalse();
})->with([
    'star' => ['star', CommitAction::Star],
    'pasteis' => ['pasteis', CommitAction::Pasteis],
]);

it('knows no other commit action', function (): void {
    expect(Path::parse('devnull://@dseguy/commits/abc/other')->commitAction())->toBeNull();
});

it('rejects paths without a user', function (string $url): void {
    Path::parse($url);
})->with([
    'devnull://',
    'devnull://dseguy',
    'devnull://@',
    'devnull://@dse_guy',
])->throws(InvalidArgumentException::class, 'Expected devnull://@username, got');

it('rejects unexpected segments', function (): void {
    Path::parse('devnull://@dseguy/commits/a?b');
})->throws(InvalidArgumentException::class, 'Unexpected segment in devnull://@dseguy/commits/a?b.');
