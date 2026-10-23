<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Signing;

use RuntimeException;

/**
 * Signs with `ssh-keygen -Y sign`: the private key never goes through PHP, ssh-keygen or the agent reads it.
 */
final readonly class SshKeygenSigner implements Signer
{
    public function __construct(
        private string $binary = 'ssh-keygen',
    ) {}

    public function sign(string $message, string $namespace, string $key): string
    {
        $temporary = null;
        $file = $key;

        if (!is_file($key)) {
            $temporary = tempnam(sys_get_temp_dir(), 'devnull') ?: throw new RuntimeException('Could not create a temporary file.');
            file_put_contents($temporary, $key . "\n");
            $file = $temporary;
        }

        try {
            [$status, $armored, $error] = $this->run(['-q', '-Y', 'sign', '-n', $namespace, '-f', $file], $message);
        } finally {
            if ($temporary !== null) {
                unlink($temporary);
            }
        }

        if ($status !== 0) {
            throw new RuntimeException("Could not sign with {$key}: " . trim($error));
        }

        return (string) preg_replace(['/-----(BEGIN|END) SSH SIGNATURE-----/', '/\s+/'], '', $armored);
    }

    /**
     * @param list<string> $arguments
     * @return array{int, string, string}
     */
    private function run(array $arguments, string $input): array
    {
        $process = @proc_open([$this->binary, ...$arguments], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException("{$this->binary} is required to sign, from OpenSSH 8.2 or later.");
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $output, $error];
    }
}
