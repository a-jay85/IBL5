<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Sweep;

/**
 * Flag parser for `scripts/trade-suggestions-laugh-test.php`. Pure: the caller
 * passes the environment, so the parser does no I/O.
 */
final class LeagueSweepArgs
{
    public const string USAGE = 'usage: php scripts/trade-suggestions-laugh-test.php [--max-teams=N] [--host=HOST] [--db=NAME] [--user=USER] [--pass=PASS]';

    private const array FLAGS = ['max-teams', 'host', 'db', 'user', 'pass'];

    /**
     * @param list<string> $argv the script's arguments without the script name
     * @param array<string, string> $env DB_HOST, DB_NAME, DB_USER and DB_PASS defaults
     * @return array{maxTeams: int, host: string, db: string, user: string, pass: string}
     */
    public static function parse(array $argv, array $env = []): array
    {
        $values = [
            'max-teams' => (string) \League\League::MAX_REAL_TEAMID,
            'host' => $env['DB_HOST'] ?? '',
            'db' => $env['DB_NAME'] ?? '',
            'user' => $env['DB_USER'] ?? '',
            'pass' => $env['DB_PASS'] ?? '',
        ];
        foreach ($argv as $arg) {
            if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m) !== 1 || !in_array($m[1], self::FLAGS, true)) {
                throw new \InvalidArgumentException("Unknown or malformed flag: {$arg}");
            }
            $values[$m[1]] = $m[2];
        }

        $raw = $values['max-teams'];
        if (preg_match('/^[0-9]+$/', $raw) !== 1 || (int) $raw < 1) {
            throw new \InvalidArgumentException("--max-teams must be a positive integer, got '{$raw}'");
        }

        return [
            'maxTeams' => (int) $raw,
            'host' => $values['host'],
            'db' => $values['db'],
            'user' => $values['user'],
            'pass' => $values['pass'],
        ];
    }
}
