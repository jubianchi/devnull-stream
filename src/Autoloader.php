<?php

declare(strict_types=1);

namespace Jubianchi\DevNull;

use Closure;
use PhpToken;
use Throwable;

/**
 * Loads DevNull\<user>\<Class> by running the PHP snippet of @user's latest commit that declares it, and
 * DevNull\<user>\v<id>\<Class> from the commit of @user whose id starts with <id>.
 *
 * It runs code from the Internet, so it only ever looks at the commits of the user the class is named after.
 */
final class Autoloader
{
    public const string NAMESPACE = 'DevNull';

    /** @var (Closure(Settings): Client)|null */
    private static ?Closure $clientFactory = null;

    private static bool $registered = false;

    public static function register(): void
    {
        if (!self::$registered) {
            spl_autoload_register(self::load(...));
            self::$registered = true;
        }
    }

    /**
     * Replaces how the autoloader builds its client, e.g. to use another transport; null restores the default.
     *
     * @param (Closure(Settings): Client)|null $factory
     */
    public static function useClientFactory(?Closure $factory): void
    {
        self::$clientFactory = $factory;
    }

    public static function load(string $class): void
    {
        $parts = explode('\\', ltrim($class, '\\'));
        $version = count($parts) === 4 && preg_match('/^v([0-9a-f]{8,32})$/i', $parts[2], $match) === 1 ? strtolower($match[1]) : null;

        if ((count($parts) !== 3 && $version === null) || strcasecmp($parts[0], self::NAMESPACE) !== 0 || preg_match('/^[A-Za-z0-9_]+$/', $parts[1]) !== 1) {
            return;
        }

        // GitHub usernames have dashes, which namespaces can't have, and no underscores.
        $user = str_replace('_', '-', $parts[1]);
        $namespace = implode('\\', array_slice($parts, 0, -1));

        try {
            $client = (self::$clientFactory ?? Client::create(...))(Settings::resolve());

            foreach (self::commits($client, $user, $version) as $commit) {
                $body = is_string($commit['body'] ?? null) ? $commit['body'] : '';
                $id = is_string($commit['id'] ?? null) ? $commit['id'] : '?';

                foreach (self::snippets($body) as $snippet) {
                    if (self::run($snippet, $namespace, $class, $id)) {
                        return;
                    }
                }
            }
        } catch (Throwable $e) {
            self::warn("devnull: could not load {$class}: {$e->getMessage()}");
        }
    }

    /**
     * @user's commits from the latest, or only those whose id, without its dashes, starts with $version: a whole id
     * is fetched directly.
     *
     * @return iterable<array<mixed>>
     */
    private static function commits(Client $client, string $user, ?string $version): iterable
    {
        if ($version !== null && strlen($version) === 32) {
            $id = implode('-', [substr($version, 0, 8), substr($version, 8, 4), substr($version, 12, 4), substr($version, 16, 4), substr($version, 20)]);
            $commit = $client->get("/api/v1/commits/{$id}")['data'] ?? null;
            $author = is_array($commit) ? $commit['author'] ?? null : null;

            if (is_array($commit) && is_array($author) && is_string($author['username'] ?? null) && strcasecmp($author['username'], $user) === 0) {
                yield $commit;
            }

            return;
        }

        foreach ($client->pages("/api/v1/users/{$user}/commits") as $page) {
            foreach ($page as $commit) {
                $id = is_string($commit['id'] ?? null) ? strtolower(str_replace('-', '', $commit['id'])) : '';

                if ($version === null || str_starts_with($id, $version)) {
                    yield $commit;
                }
            }
        }
    }

    /**
     * The ```php fences of a commit, or what follows its <?php tag.
     *
     * @return list<string>
     */
    public static function snippets(string $body): array
    {
        if (preg_match_all('/^```php[ \t]*\R(.*?)^```/ms', $body, $fences) > 0) {
            return array_map(
                static fn(string $code): string => str_starts_with(ltrim($code), '<?php') ? $code : "<?php\n{$code}",
                $fences[1],
            );
        }

        $start = strpos($body, '<?php');

        return $start === false ? [] : [substr($body, $start)];
    }

    /**
     * Runs the snippet, in $namespace unless it names its own, when it declares $class.
     */
    private static function run(string $snippet, string $namespace, string $class, string $id): bool
    {
        $tokens = array_values(PhpToken::tokenize($snippet));
        $declared = self::declarations($tokens, $namespace);

        if (!in_array(strtolower(ltrim($class, '\\')), array_map(strtolower(...), $declared), true)) {
            return false;
        }

        $code = self::hasNamespace($tokens) ? $snippet : self::inNamespace($tokens, $namespace);

        try {
            eval('?>' . $code);
        } catch (Throwable $e) {
            self::warn("devnull: commit {$id} failed to declare {$class}: {$e->getMessage()}");

            return false;
        }

        return class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false) || enum_exists($class, false);
    }

    /**
     * The classes, interfaces, traits and enums the snippet declares, with their namespace.
     *
     * @param list<PhpToken> $tokens
     * @return list<string>
     */
    private static function declarations(array $tokens, string $namespace): array
    {
        $tokens = array_values(array_filter($tokens, static fn(PhpToken $token): bool => !$token->isIgnorable()));
        $declared = [];

        foreach ($tokens as $i => $token) {
            $next = $tokens[$i + 1] ?? null;
            $previous = $tokens[$i - 1] ?? null;

            if ($token->is(T_NAMESPACE) && $next?->is([T_STRING, T_NAME_QUALIFIED]) === true) {
                $namespace = $next->text;
            }

            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && $next?->is(T_STRING) === true && $previous?->is(T_DOUBLE_COLON) !== true) {
                $declared[] = "{$namespace}\\{$next->text}";
            }
        }

        return $declared;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private static function hasNamespace(array $tokens): bool
    {
        return array_any($tokens, static fn(PhpToken $token): bool => $token->is(T_NAMESPACE));
    }

    /**
     * Puts the snippet in the namespace, after its opening tag and its declare() statements.
     *
     * @param list<PhpToken> $tokens
     */
    private static function inNamespace(array $tokens, string $namespace): string
    {
        $at = 0;

        for ($i = 0, $inDeclare = false; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if ($inDeclare) {
                $inDeclare = $token->text !== ';';
                $at = $i + 1;
            } elseif ($token->is(T_DECLARE)) {
                $inDeclare = true;
            } elseif ($token->is(T_OPEN_TAG)) {
                $at = $i + 1;
            } elseif (!$token->isIgnorable()) {
                break;
            }
        }

        return self::text(array_slice($tokens, 0, $at)) . "\nnamespace {$namespace};\n" . self::text(array_slice($tokens, $at));
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private static function text(array $tokens): string
    {
        return implode('', array_map(static fn(PhpToken $token): string => $token->text, $tokens));
    }

    private static function warn(string $message): void
    {
        trigger_error($message, E_USER_WARNING);
    }
}
