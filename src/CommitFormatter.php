<?php

declare(strict_types=1);

namespace Jubianchi\DevNull;

final class CommitFormatter
{
    /**
     * @param array<mixed> $commit
     */
    public static function format(array $commit): string
    {
        $author = $commit['author'] ?? null;
        $who = is_array($author)
            ? trim(self::string($author['name'] ?? '') . ' <@' . self::string($author['username'] ?? '') . '>')
            : '[deleted]';
        $parent = self::string($commit['parent_id'] ?? '');
        $body = preg_replace('/^/m', '    ', self::string($commit['body'] ?? ''));

        return 'commit ' . self::string($commit['id'] ?? '') . "\n"
            . ($parent !== '' ? "Reply:  {$parent}\n" : '')
            . "Author: {$who}\n"
            . 'Date:   ' . self::string($commit['created_at'] ?? '') . "\n"
            . sprintf("Stars:  %d  Replies: %d\n\n", self::int($commit['stars_count'] ?? 0), self::int($commit['replies_count'] ?? 0))
            . "{$body}\n\n";
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
