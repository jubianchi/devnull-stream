<?php

declare(strict_types=1);

use Jubianchi\DevNull\Autoloader;
use Jubianchi\DevNull\Client;
use Jubianchi\DevNull\Tests\Fakes\FakeTransport;

/**
 * Points the autoloader at a fake API answering with these commits of @dseguy.
 *
 * @param list<array<string, mixed>> ...$pages
 */
function commitsOfDseguy(array ...$pages): FakeTransport
{
    $transport = new FakeTransport();
    for ($i = 0; $i < count($pages); $i++) {
        $uri = '/api/v1/users/dseguy/commits' . ($i === 0 ? '' : "?cursor={$i}");
        $transport->on('GET', $uri, ['data' => $pages[$i], 'meta' => ['next_cursor' => $i + 1 < count($pages) ? (string) ($i + 1) : null]]);
    }

    Autoloader::useClientFactory(static fn(): Client => client($transport));

    return $transport;
}

/**
 * DevNull\<user>\<Class>, as a string static analysis can't resolve: PHPStan would otherwise autoload it from
 * dev-null.sh.
 */
function devnull(string $class): string
{
    return "DevNull\\{$class}";
}

beforeEach(function (): void {
    Autoloader::register();
});

afterEach(function (): void {
    Autoloader::useClientFactory(null);
});

it('loads a class from the snippet of a commit', function (): void {
    commitsOfDseguy([commit('a', ['body' => "Look at this:\n<?php\nclass Hello { public static function world(): string { return 'Hello from /dev/null!'; } }"])]);

    $world = devnull('dseguy\Hello')::world();

    expect(is_string($world) ? $world : null)->toBe('Hello from /dev/null!');
});

it('loads interfaces, traits and enums too', function (): void {
    commitsOfDseguy([commit('a', ['body' => "<?php\ninterface Shape {}\ntrait Named {}\nenum Suit { case Hearts; }"])]);

    expect(interface_exists(devnull('dseguy\Shape')))->toBeTrue()
        ->and(trait_exists(devnull('dseguy\Named')))->toBeTrue()
        ->and(enum_exists(devnull('dseguy\Suit')))->toBeTrue();
});

it('runs the ```php fences of a commit', function (): void {
    commitsOfDseguy([commit('a', ['body' => "Two of them:\n```php\nclass Fenced {}\n```\nand\n```php\n<?php\nclass Tagged {}\n```\n#php"])]);

    expect(class_exists(devnull('dseguy\Fenced')))->toBeTrue()
        ->and(class_exists(devnull('dseguy\Tagged')))->toBeTrue();
});

it('runs the latest commit declaring the class, looking through the pages', function (): void {
    $api = commitsOfDseguy(
        [commit('a', ['body' => 'No code here']), commit('b', ['body' => '<?php class Other {}'])],
        [commit('c', ['body' => "<?php class Versioned { const VERSION = 2; }"]), commit('d', ['body' => "<?php class Versioned { const VERSION = 1; }"])],
    );

    expect(constant(devnull('dseguy\Versioned') . '::VERSION'))->toBe(2)
        ->and(class_exists(devnull('dseguy\Other'), false))->toBeFalse()
        ->and($api->requests)->toHaveCount(2);
});

it('keeps the declare() statements first', function (): void {
    commitsOfDseguy([commit('a', ['body' => "<?php\ndeclare(strict_types=1);\n\nclass Strict { public static function add(int \$a): int { return \$a + 1; } }"])]);

    expect(fn(): mixed => devnull('dseguy\Strict')::add('1'))->toThrow(TypeError::class);
});

it('runs a namespaced snippet only when it declares the class', function (): void {
    commitsOfDseguy([
        commit('a', ['body' => '<?php namespace Elsewhere; class Spoofed {}']),
        commit('b', ['body' => '<?php namespace DevNull\dseguy; class Spoofed {}']),
    ]);

    expect(class_exists(devnull('dseguy\Spoofed')))->toBeTrue()
        ->and(class_exists('Elsewhere\Spoofed', false))->toBeFalse();
});

it("maps underscores to the dashes of usernames", function (): void {
    $transport = new FakeTransport()->on('GET', '/api/v1/users/jean-pierre/commits', ['data' => [commit('a', ['body' => '<?php class Dashed {}'])]]);
    Autoloader::useClientFactory(static fn(): Client => client($transport));

    expect(class_exists(devnull('jean_pierre\Dashed')))->toBeTrue();
});

it('ignores classes outside DevNull\<user>\<Class>', function (string $class): void {
    $api = commitsOfDseguy([commit('a', ['body' => '<?php class Ignored {}'])]);

    expect(class_exists($class))->toBeFalse()
        ->and($api->requests)->toBe([]);
})->with(['Ignored', 'Elsewhere\dseguy\Ignored', 'DevNull\Ignored', 'DevNull\dseguy\Deeper\Ignored']);

it('finds nothing quietly', function (): void {
    commitsOfDseguy([commit('a', ['body' => '<?php class Something {}'])]);

    [$result, $warnings] = withWarnings(fn(): bool => class_exists(devnull('dseguy\Nothing')));

    expect($result)->toBeFalse()
        ->and($warnings)->toBe([]);
});

it('warns about a broken snippet and tries the next commit', function (): void {
    commitsOfDseguy([commit('a', ['body' => '<?php class Broken { oops }']), commit('b', ['body' => '<?php class Broken {}'])]);

    [$result, $warnings] = withWarnings(fn(): bool => class_exists(devnull('dseguy\Broken')));

    expect($result)->toBeTrue()
        ->and($warnings)->toHaveCount(1)
        ->and($warnings[0])->toStartWith('devnull: commit a failed to declare DevNull\dseguy\Broken: syntax error');
});

it('warns when the API fails', function (): void {
    $transport = new FakeTransport()->on('GET', '/api/v1/users/dseguy/commits', ['message' => 'Too many requests.'], 429);
    Autoloader::useClientFactory(static fn(): Client => client($transport));

    [$result, $warnings] = withWarnings(fn(): bool => class_exists(devnull('dseguy\Limited')));

    expect($result)->toBeFalse()
        ->and($warnings)->toBe(['devnull: could not load DevNull\dseguy\Limited: devnull: GET /api/v1/users/dseguy/commits failed (HTTP 429): Too many requests.']);
});
