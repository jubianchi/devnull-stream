<?php

declare(strict_types=1);

use Jubianchi\DevNull\CommitFormatter;

it('formats a commit as git log does', function (): void {
    expect(CommitFormatter::format([
        'id' => 'abc',
        'parent_id' => null,
        'author' => ['name' => 'Damien Seguy', 'username' => 'dseguy'],
        'created_at' => '2026-10-18T10:00:00Z',
        'stars_count' => 3,
        'replies_count' => 1,
        'body' => "Hello\nworld",
    ]))->toBe(<<<'TEXT'
        commit abc
        Author: Damien Seguy <@dseguy>
        Date:   2026-10-18T10:00:00Z
        Stars:  3  Replies: 1

            Hello
            world


        TEXT);
});

it('shows the parent of a reply', function (): void {
    expect(CommitFormatter::format([
        'id' => 'def',
        'parent_id' => 'abc',
        'author' => ['username' => 'jubianchi'],
        'created_at' => '2026-10-18T10:00:00Z',
        'body' => 'Agreed!',
    ]))->toBe(<<<'TEXT'
        commit def
        Reply:  abc
        Author: <@jubianchi>
        Date:   2026-10-18T10:00:00Z
        Stars:  0  Replies: 0

            Agreed!


        TEXT);
});

it('shows deleted authors', function (): void {
    expect(CommitFormatter::format([
        'id' => 'abc',
        'parent_id' => null,
        'author' => null,
        'created_at' => '2026-10-18T10:00:00Z',
        'body' => 'Gone',
    ]))->toContain("Author: [deleted]\n");
});
