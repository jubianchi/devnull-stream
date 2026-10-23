<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Tests\Support;

use RuntimeException;

/**
 * A `php -S` server answering every request with what it received, as JSON.
 */
final class EchoServer
{
    /** @var resource|null */
    private static $process = null;

    private static string $url = '';

    public static function start(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0') ?: throw new RuntimeException('No free port.');
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__ . '/echo-server.php'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes,
        );
        self::$process = is_resource($process) ? $process : throw new RuntimeException('Could not start php -S.');

        for ($i = 0; $i < 100 && @fsockopen('127.0.0.1', $port) === false; $i++) {
            usleep(20_000);
        }

        self::$url = "http://127.0.0.1:{$port}";
    }

    public static function stop(): void
    {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            proc_close(self::$process);
            self::$process = null;
        }
    }

    public static function url(string $uri = ''): string
    {
        return self::$url . $uri;
    }

    /**
     * @return array<mixed>
     */
    public static function decode(string $body): array
    {
        return (array) json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
