<?php

declare(strict_types=1);

namespace Jubianchi\DevNull;

use Closure;
use Generator;
use RuntimeException;
use Throwable;

/**
 * The devnull:// stream wrapper: a thin adapter from PHP's file functions to the client.
 */
final class StreamWrapper
{
    public const string SCHEME = Path::SCHEME;

    /**
     * What the last write got back: the link to the commit or reply it created, or '' when it failed.
     */
    public private(set) static string $lastResponse = '';

    /** @var resource|null */
    public $context;

    /** @var (Closure(Settings): Client)|null */
    private static ?Closure $clientFactory = null;

    private string $buffer = '';

    /** @var Generator<int, list<array<mixed>>>|null */
    private ?Generator $pages = null;

    private bool $consumed = false;

    private ?Path $writing = null;

    /** @var list<string> */
    private array $entries = [];

    public static function register(): void
    {
        if (!in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }
    }

    /**
     * Replaces how the wrapper builds its client, e.g. to use another transport; null restores the default.
     *
     * @param (Closure(Settings): Client)|null $factory
     */
    public static function useClientFactory(?Closure $factory): void
    {
        self::$clientFactory = $factory;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        try {
            $target = Path::parse($path);

            if ($mode[0] !== 'r') {
                $this->writing = $target->isCommitTarget()
                    ? $target
                    : throw new RuntimeException("Write to devnull://@you to commit, or to devnull://@user/commits/<id> to reply, not to {$path}.");

                return true;
            }

            $listing = $target->listingUri();
            $commitId = $target->commitId();

            match (true) {
                $listing !== null => $this->pages = $this->client()->pages($listing),
                $commitId !== null => $this->buffer = CommitFormatter::format(self::data($this->client()->get("/api/v1/commits/{$commitId}"))),
                default => throw new RuntimeException("Nothing to read at {$path}."),
            };

            return true;
        } catch (Throwable $e) {
            self::warn($e->getMessage());

            return false;
        }
    }

    public function stream_read(int $count): string
    {
        try {
            while (strlen($this->buffer) < $count && $this->pages !== null) {
                if ($this->consumed) {
                    $this->pages->next();
                    $this->consumed = false;
                }

                if (!$this->pages->valid()) {
                    $this->pages = null;

                    break;
                }

                $this->buffer .= implode('', array_map(CommitFormatter::format(...), $this->pages->current()));
                $this->consumed = true;
            }
        } catch (Throwable $e) {
            $this->pages = null;
            self::warn($e->getMessage());
        }

        $chunk = substr($this->buffer, 0, $count);
        $this->buffer = substr($this->buffer, strlen($chunk));

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->buffer === '' && $this->pages === null;
    }

    public function stream_write(string $data): int
    {
        $this->buffer .= $data;

        return strlen($data);
    }

    /**
     * The whole message is sent once, when the stream closes, so several fwrite() calls make a single commit.
     */
    public function stream_close(): void
    {
        if ($this->writing === null || trim($this->buffer) === '') {
            return;
        }

        try {
            self::$lastResponse = $this->client()->commit($this->buffer, $this->writing->commitId() ?? '');
        } catch (Throwable $e) {
            self::$lastResponse = '';
            self::warn($e->getMessage());
        }
    }

    public function stream_stat(): false
    {
        return false;
    }

    /**
     * @return array<string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        try {
            $target = Path::parse($path);
            $commitId = $target->commitId();

            [$resource, $isDir] = match (true) {
                $target->listingUri() !== null => [self::data($this->client()->get("/api/v1/users/{$target->user}")), true],
                $commitId !== null => [self::data($this->client()->get("/api/v1/commits/{$commitId}")), false],
                default => [null, false],
            };
        } catch (Throwable) {
            $resource = null;
        }

        if ($resource === null) {
            if (($flags & STREAM_URL_STAT_QUIET) === 0) {
                self::warn("No such commit or user: {$path}");
            }

            return false;
        }

        $time = is_string($resource['created_at'] ?? null) ? (int) strtotime($resource['created_at']) : 0;
        $size = $isDir ? 0 : strlen(CommitFormatter::format($resource));

        return ['dev' => 0, 'ino' => 0, 'mode' => $isDir ? 0040555 : 0100644, 'nlink' => 1, 'uid' => 0, 'gid' => 0,
            'rdev' => 0, 'size' => $size, 'atime' => $time, 'mtime' => $time, 'ctime' => $time, 'blksize' => -1, 'blocks' => -1];
    }

    public function dir_opendir(string $path, int $options): bool
    {
        try {
            $listing = Path::parse($path)->listingUri() ?? throw new RuntimeException("Not a directory: {$path}");

            $this->entries = [];
            foreach ($this->client()->pages($listing) as $page) {
                foreach ($page as $commit) {
                    if (is_string($commit['id'] ?? null)) {
                        $this->entries[] = $commit['id'];
                    }
                }
            }

            return true;
        } catch (Throwable $e) {
            self::warn($e->getMessage());

            return false;
        }
    }

    public function dir_readdir(): string|false
    {
        return array_shift($this->entries) ?? false;
    }

    public function dir_rewinddir(): bool
    {
        return false;
    }

    public function dir_closedir(): bool
    {
        $this->entries = [];

        return true;
    }

    /**
     * touch() stars a commit (.../star) or gives it a pastel de nata (.../pasteis).
     */
    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        if ($option !== STREAM_META_TOUCH) {
            return false;
        }

        try {
            [$commitId, $action] = Path::parse($path)->commitAction()
                ?? throw new RuntimeException("touch() a commit's star or pasteis, not {$path}.");

            $this->client()->signed('POST', "/api/v1/commits/{$commitId}/{$action->value}");

            return true;
        } catch (Throwable $e) {
            self::warn($e->getMessage());

            return false;
        }
    }

    /**
     * unlink() unstars a commit (.../star), or deletes one of yours.
     */
    public function unlink(string $path): bool
    {
        try {
            $target = Path::parse($path);
            $commitId = $target->commitId();
            [$starred, $action] = $target->commitAction() ?? [null, null];

            $uri = match (true) {
                $action === CommitAction::Star => "/api/v1/commits/{$starred}/star",
                $commitId !== null => "/api/v1/commits/{$commitId}",
                default => throw new RuntimeException("unlink() a commit or its star, not {$path}."),
            };

            $this->client()->signed('DELETE', $uri);

            return true;
        } catch (Throwable $e) {
            self::warn($e->getMessage());

            return false;
        }
    }

    private function client(): Client
    {
        $options = is_resource($this->context) ? (stream_context_get_options($this->context)[self::SCHEME] ?? []) : [];
        $settings = Settings::resolve(is_array($options) ? $options : []);

        return (self::$clientFactory ?? Client::create(...))($settings);
    }

    /**
     * @param array<mixed> $response
     * @return array<mixed>
     */
    private static function data(array $response): array
    {
        return is_array($response['data'] ?? null) ? $response['data'] : throw new RuntimeException('devnull: unexpected response, without data.');
    }

    private static function warn(string $message): void
    {
        trigger_error($message, E_USER_WARNING);
    }
}
