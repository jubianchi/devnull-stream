<?php

declare(strict_types=1);

namespace Jubianchi\DevNull;

use InvalidArgumentException;

/**
 * "devnull://@user[/replies|/commits/<id>[/star|/pasteis]]", as on the site.
 */
final readonly class Path
{
    public const string SCHEME = 'devnull';

    /**
     * @param list<string> $tail
     */
    private function __construct(
        public string $user,
        public array $tail,
    ) {}

    public static function parse(string $url): self
    {
        $rest = substr($url, strlen(self::SCHEME) + 3);
        $parts = array_values(array_filter(explode('/', $rest), static fn(string $part): bool => $part !== ''));

        if ($parts === [] || preg_match('/^@([A-Za-z0-9-]+)$/', $parts[0], $match) !== 1) {
            throw new InvalidArgumentException("Expected devnull://@username, got {$url}.");
        }

        $tail = array_slice($parts, 1);

        foreach ($tail as $part) {
            if (preg_match('/^[A-Za-z0-9-]+$/', $part) !== 1) {
                throw new InvalidArgumentException("Unexpected segment in {$url}.");
            }
        }

        return new self($match[1], $tail);
    }

    public function listingUri(): ?string
    {
        return match ($this->tail) {
            [] => "/api/v1/users/{$this->user}/commits",
            ['replies'] => "/api/v1/users/{$this->user}/commits?replies=1",
            default => null,
        };
    }

    public function commitId(): ?string
    {
        return count($this->tail) === 2 && $this->tail[0] === 'commits' ? $this->tail[1] : null;
    }

    /**
     * @return array{string, CommitAction}|null
     */
    public function commitAction(): ?array
    {
        if (count($this->tail) !== 3 || $this->tail[0] !== 'commits') {
            return null;
        }

        $action = CommitAction::tryFrom($this->tail[2]);

        return $action === null ? null : [$this->tail[1], $action];
    }

    public function isCommitTarget(): bool
    {
        return $this->tail === [] || $this->commitId() !== null;
    }
}
