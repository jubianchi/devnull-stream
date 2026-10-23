<?php

declare(strict_types=1);

/**
 * @param list<string> $command
 * @return array{int, string}
 */
function runPhp(array $command): array
{
    $process = proc_open([PHP_BINARY, ...$command], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2))
        ?: throw new RuntimeException('Could not run php.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

    return [proc_close($process), $output];
}

it('registers devnull:// as soon as the autoloader is loaded', function (): void {
    [$status, $output] = runPhp(['-r', 'require "vendor/autoload.php"; echo in_array("devnull", stream_get_wrappers(), true) ? "yes" : "no";']);

    expect($status)->toBe(0)
        ->and($output)->toBe('yes');
});

it('registers devnull:// only once', function (): void {
    [$status, $output] = runPhp(['-r', 'require "vendor/autoload.php"; Jubianchi\DevNull\StreamWrapper::register(); echo "ok";']);

    expect($status)->toBe(0)
        ->and($output)->toBe('ok');
});

it('ships a devnull command', function (): void {
    [$status, $output] = runPhp(['bin/devnull']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Usage: devnull authorize [device name]');
});
