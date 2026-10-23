<?php

declare(strict_types=1);

namespace Jubianchi\DevNull;

use Generator;
use InvalidArgumentException;
use Jubianchi\DevNull\Http\NativeTransport;
use Jubianchi\DevNull\Http\Request;
use Jubianchi\DevNull\Http\Transport;
use Jubianchi\DevNull\Keys\KeyRing;
use Jubianchi\DevNull\Keys\LocalKeyRing;
use Jubianchi\DevNull\Keys\PublicKey;
use Jubianchi\DevNull\Signing\Payload;
use Jubianchi\DevNull\Signing\Signer;
use Jubianchi\DevNull\Signing\SshKeygenSigner;
use RuntimeException;

final readonly class Client
{
    private string $url;

    /**
     * @param string|null $key the key to sign with (private key file, or public key held by the agent); null
     *                         to pick one from the key ring
     */
    public function __construct(
        string $url,
        private Transport $transport = new NativeTransport(),
        private Signer $signer = new SshKeygenSigner(),
        private KeyRing $keys = new LocalKeyRing(),
        private ?string $key = null,
    ) {
        $this->url = rtrim($url, '/');

        if (preg_match('#^https://|^http://(localhost|127\.0\.0\.1)(:\d+)?$#', $this->url) !== 1) {
            throw new InvalidArgumentException("Refusing to talk to {$this->url}: it is not an https:// URL.");
        }
    }

    public static function create(Settings $settings): self
    {
        return new self($settings->url, key: $settings->key);
    }

    /**
     * @return array<mixed>
     */
    public function get(string $uri): array
    {
        return self::json($this->request('GET', $uri));
    }

    /**
     * Yields the `data` of each page of a list, fetching the next one only when asked to.
     *
     * @return Generator<int, list<array<mixed>>>
     */
    public function pages(string $uri): Generator
    {
        for ($next = $uri; $next !== null;) {
            $page = $this->get($next);
            yield self::items($page['data'] ?? []);

            $meta = $page['meta'] ?? [];
            $cursor = is_array($meta) ? ($meta['next_cursor'] ?? null) : null;
            $next = is_string($cursor) || is_int($cursor) ? self::withCursor($next, (string) $cursor) : null;
        }
    }

    /**
     * Commits, or replies to $replyTo, through the endpoint `curl dev-null.sh/commit | bash` uses: any SSH key on
     * your GitHub account signs. Returns the link to what was created.
     */
    public function commit(string $message, string $replyTo = ''): string
    {
        $payload = Payload::commit(time(), Payload::nonce(), $replyTo, $message);
        $signature = $this->signer->sign($payload, Payload::COMMIT_NAMESPACE, $this->commitKey());

        return trim($this->request('POST', '/api/cli/commits', $payload, [
            'Content-Type: text/plain; charset=utf-8',
            "X-SSH-Signature: {$signature}",
        ]));
    }

    /**
     * The configured key, or the first local key that dev-null.sh finds on the GitHub account of one of its users.
     */
    private function commitKey(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $candidates = $this->keys->keys();

        if ($candidates === []) {
            throw new RuntimeException('No Ed25519 or RSA SSH key found in your SSH agent or in ~/.ssh.');
        }

        $found = PublicKey::id($this->request('POST', '/api/cli/key-lookups', implode("\n", array_keys($candidates)) . "\n", [
            'Content-Type: text/plain; charset=utf-8',
        ]));

        return $candidates[$found] ?? throw new RuntimeException('None of your SSH keys is on the GitHub account of a /dev/null user.');
    }

    /**
     * @param list<string> $headers
     */
    private function request(string $method, string $uri, string $body = '', array $headers = []): string
    {
        $response = $this->transport->send(new Request(
            $method,
            $this->url . $uri,
            [...$headers, 'Accept: application/json', 'User-Agent: devnull-php-stream'],
            $body,
        ));

        if (!$response->isSuccessful()) {
            $error = json_decode($response->body, true);
            $message = is_array($error) ? ($error['message'] ?? (is_array($error['error'] ?? null) ? $error['error']['message'] ?? null : null)) : null;

            throw new ApiException($method, $uri, $response->status, is_string($message) ? $message : trim($response->body));
        }

        return $response->body;
    }

    /**
     * @return array<mixed>
     */
    private static function json(string $body): array
    {
        return $body === '' ? [] : (array) json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array<mixed>>
     */
    private static function items(mixed $data): array
    {
        return is_array($data) ? array_values(array_filter($data, is_array(...))) : [];
    }

    private static function withCursor(string $uri, string $cursor): string
    {
        $uri = rtrim((string) preg_replace('/([?&])cursor=[^&]*&?/', '$1', $uri), '?&');

        return $uri . (str_contains($uri, '?') ? '&' : '?') . 'cursor=' . rawurlencode($cursor);
    }
}
