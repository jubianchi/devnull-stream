<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Console;

use Closure;
use Jubianchi\DevNull\Authorization\DeviceAuthorization;
use Jubianchi\DevNull\Authorization\DeviceCodeFlow;
use Jubianchi\DevNull\Authorization\FallbackFlow;
use Jubianchi\DevNull\Authorization\Pkce;
use Jubianchi\DevNull\Authorization\PkceFlow;
use Jubianchi\DevNull\Client;
use Jubianchi\DevNull\Clock\Clock;
use Jubianchi\DevNull\Clock\SystemClock;
use Throwable;

/**
 * `devnull authorize [device name]`: approves your key for stars, pastéis and deletion, once.
 */
final readonly class Application
{
    /**
     * @param (Closure(): Pkce)|null $pkce
     */
    public function __construct(
        private Client $client,
        private Clock $clock = new SystemClock(),
        private ?Closure $pkce = null,
    ) {}

    /**
     * @param list<string> $argv
     * @param resource $input
     * @param resource $output
     */
    public function run(array $argv, $input, $output): int
    {
        if (($argv[1] ?? null) !== 'authorize') {
            fwrite($output, "Usage: devnull authorize [device name]\n\n");
            fwrite($output, "Approves your SSH key (DEVNULL_KEY, or your agent's first key) as a device of your dev-null.sh account,\n");
            fwrite($output, "so the devnull:// stream wrapper can star, give pastéis and delete.\n");

            return 1;
        }

        $deviceName = trim(implode(' ', array_slice($argv, 2))) ?: 'PHP stream wrapper';

        $flow = new FallbackFlow(
            new DeviceCodeFlow($this->client, static function (DeviceAuthorization $authorization) use ($output): void {
                fwrite($output, "Open and approve:\n\n   {$authorization->verificationUriComplete}\n\n");
                fwrite($output, "or open {$authorization->verificationUri} and type the code {$authorization->userCode}\n\n");
                fwrite($output, 'Waiting for the approval…');
            }, $this->clock),
            new PkceFlow($this->client, static function (string $url) use ($input, $output): string {
                fwrite($output, "1. Open and approve:\n\n   {$url}\n\n");
                fwrite($output, "2. Your browser then fails to open a devnull://authorize?code=… link: copy it from the address bar\n");
                fwrite($output, "   (or the Location header of the POST in the developer tools' Network tab) and paste it here: ");

                return (string) fgets($input);
            }, $this->pkce),
        );

        try {
            $me = $flow->authorize($deviceName);
        } catch (Throwable $e) {
            fwrite($output, "\n{$e->getMessage()}\n");

            return 1;
        }

        $data = $me['data'] ?? null;
        $username = is_array($data) && is_string($data['username'] ?? null) ? $data['username'] : '?';
        fwrite($output, "\nApproved as a device of @{$username}.\n");

        return 0;
    }
}
