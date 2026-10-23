<?php

declare(strict_types=1);

namespace Jubianchi\DevNull\Keys;

/**
 * The keys of the SSH agent (`ssh-add -L`) and of ~/.ssh; only public halves are read.
 */
final readonly class LocalKeyRing implements KeyRing
{
    private string $home;

    public function __construct(?string $home = null)
    {
        $this->home = $home ?? (string) getenv('HOME');
    }

    public function keys(): array
    {
        $keys = [];

        exec('ssh-add -L 2>/dev/null', $agent);

        foreach ($agent as $line) {
            $id = PublicKey::id($line);
            $keys[$id] = $id;
        }

        foreach (glob("{$this->home}/.ssh/*.pub") ?: [] as $public) {
            $private = substr($public, 0, -4);

            if (is_file($private)) {
                $keys[PublicKey::id((string) file_get_contents($public))] = $private;
            }
        }

        return array_filter($keys, PublicKey::isSupported(...), ARRAY_FILTER_USE_KEY);
    }

    public function publicKeyOf(string $key): string
    {
        return is_file($key) ? PublicKey::id((string) file_get_contents("{$key}.pub")) : PublicKey::id($key);
    }
}
