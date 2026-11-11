<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Timing;

/**
 * Flag parser for `scripts/trade-suggestions-timing.php`. Pure: the caller
 * passes the environment, so the parser does no I/O.
 */
final class SnapshotTimingArgs
{
    public const string USAGE = 'usage: php scripts/trade-suggestions-timing.php [--tid=N] [--repeat=N] [--host=HOST] [--db=NAME] [--user=USER] [--pass=PASS]';

    private const array FLAGS = ['tid', 'repeat', 'host', 'db', 'user', 'pass'];

    /**
     * @param list<string> $argv the script's arguments without the script name
     * @param array<string, string> $env DB_HOST, DB_NAME, DB_USER and DB_PASS defaults
     * @return array{tid: int, repeat: int, host: string, db: string, user: string, pass: string}
     */
    public static function parse(array $argv, array $env = []): array
    {
        $values = [
            'tid' => '1',
            'repeat' => '3',
            'host' => $env['DB_HOST'] ?? '',
            'db' => $env['DB_NAME'] ?? '',
            'user' => $env['DB_USER'] ?? '',
            'pass' => $env['DB_PASS'] ?? '',
        ];
        foreach ($argv as $arg) {
            if (preg_match('/^--([a-z]+)=(.*)$/', $arg, $m) !== 1 || !in_array($m[1], self::FLAGS, true)) {
                throw new \InvalidArgumentException("Unknown or malformed flag: {$arg}");
            }
            $values[$m[1]] = $m[2];
        }

        return [
            'tid' => self::positive('tid', $values['tid']),
            'repeat' => self::positive('repeat', $values['repeat']),
            'host' => $values['host'],
            'db' => $values['db'],
            'user' => $values['user'],
            'pass' => $values['pass'],
        ];
    }

    private static function positive(string $flag, string $raw): int
    {
        if (preg_match('/^[0-9]+$/', $raw) !== 1 || (int) $raw < 1) {
            throw new \InvalidArgumentException("--{$flag} must be a positive integer, got '{$raw}'");
        }

        return (int) $raw;
    }
}
