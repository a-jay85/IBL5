<?php

declare(strict_types=1);

namespace Tests\Season;

/**
 * Runs a PHP snippet in a child process that loads the real Season\Season.
 *
 * The PHPUnit bootstrap aliases Season\Season to a mock (TestAliasesBootstrap), so a test that needs the
 * real constructor, its queries, or real settings reads has to run it in a child that loads only the
 * Composer autoloader. The snippet must echo one JSON document; the parent asserts on the decoded array.
 */
final class RealSeasonProcess
{
    private const PRELUDE = <<<'PHP'
        require $argv[1] . '/vendor/autoload.php';
        // Resolve classes from this checkout even when vendor/ is a worktree symlink.
        spl_autoload_register(static function (string $class) use ($argv): void {
            $base = str_starts_with($class, 'Tests\\')
                ? $argv[1] . '/tests/' . substr($class, 6)
                : $argv[1] . '/classes/' . $class;
            $file = str_replace('\\', '/', $base) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }, true, true);
        date_default_timezone_set('UTC');
        PHP;

    /**
     * @return array<string, mixed>
     */
    public static function run(string $snippet): array
    {
        $root = dirname(__DIR__, 2);
        $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg(self::PRELUDE . "\n" . $snippet)
            . ' ' . escapeshellarg($root) . ' 2>&1';
        $output = [];
        exec($cmd, $output, $exitCode);
        $raw = implode("\n", $output);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Child process exited ' . $exitCode . ': ' . $raw);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Child process printed non-JSON output: ' . $raw);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
