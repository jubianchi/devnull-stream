<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Tests\Support;

use RuntimeException;

/**
 * Throwaway SSH keys and agents for the tests that run the real ssh-keygen.
 */
final class SshKeys
{
    public static function available(): bool
    {
        exec('command -v ssh-keygen 2>/dev/null', $output, $status);

        return $status === 0;
    }

    public static function directory(): string
    {
        $directory = sys_get_temp_dir() . '/devnull-stream-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700);

        return $directory;
    }

    /**
     * Generates a passphrase-less key and returns the path of its private half.
     */
    public static function generate(string $directory, string $name = 'id_ed25519', string $type = 'ed25519'): string
    {
        $path = "{$directory}/{$name}";
        self::run(['ssh-keygen', '-q', '-t', $type, '-N', '', '-C', 'test', '-f', $path]);

        return $path;
    }

    public static function publicKey(string $privateKey): string
    {
        return trim((string) file_get_contents("{$privateKey}.pub"));
    }

    /**
     * Checks a bare base64 signature as dev-null.sh does, with `ssh-keygen -Y verify`.
     */
    public static function verify(string $publicKey, string $namespace, string $message, string $signature): bool
    {
        $directory = self::directory();

        try {
            file_put_contents("{$directory}/allowed_signers", "test {$publicKey}\n");
            file_put_contents("{$directory}/signature", "-----BEGIN SSH SIGNATURE-----\n" . chunk_split($signature, 70, "\n") . "-----END SSH SIGNATURE-----\n");

            $process = proc_open(
                ['ssh-keygen', '-Y', 'verify', '-f', "{$directory}/allowed_signers", '-I', 'test', '-n', $namespace, '-s', "{$directory}/signature"],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                $pipes,
            ) ?: throw new RuntimeException('Could not run ssh-keygen.');

            fwrite($pipes[0], $message);
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);

            return proc_close($process) === 0;
        } finally {
            self::remove($directory);
        }
    }

    /**
     * Starts an ssh-agent holding the key and returns its pid, or null when no agent can be started.
     */
    public static function startAgent(string $privateKey): ?int
    {
        exec('ssh-agent -s 2>/dev/null', $output, $status);

        if ($status !== 0 || preg_match('/SSH_AUTH_SOCK=([^;]+);.*SSH_AGENT_PID=(\d+);/s', implode("\n", $output), $match) !== 1) {
            return null;
        }

        putenv("SSH_AUTH_SOCK={$match[1]}");
        self::run(['ssh-add', '-q', $privateKey]);

        return (int) $match[2];
    }

    public static function stopAgent(int $pid): void
    {
        exec("kill {$pid}");
        putenv('SSH_AUTH_SOCK');
    }

    public static function remove(string $directory): void
    {
        foreach (glob("{$directory}/{,.}*", GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($directory);
    }

    /**
     * @param list<string> $command
     */
    private static function run(array $command): void
    {
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes)
            ?: throw new RuntimeException("Could not run {$command[0]}.");
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException("{$command[0]} failed: {$error}");
        }
    }
}
