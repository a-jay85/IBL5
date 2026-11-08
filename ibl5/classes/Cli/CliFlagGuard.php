<?php

declare(strict_types=1);

namespace Cli;

/**
 * Rejects unknown flags in a CLI script's $argv. Call it before the script loads
 * config.php or opens a DB connection, so a typo fails on a machine with no database.
 * The caller catches InvalidArgumentException, writes the message to STDERR, and exits 2.
 */
final class CliFlagGuard
{
    /**
     * @param array<int, string> $argv
     * @param list<string> $allowed exact flags, or value flags ending in '='
     * @return list<string>
     */
    public static function unknownFlags(array $argv, array $allowed): array
    {
        $unknown = [];
        foreach (array_slice($argv, 1) as $token) {
            if ($token === '--') {
                break;
            }
            if ($token === '-' || !str_starts_with($token, '-')) {
                continue;
            }
            if (!self::isAllowed($token, $allowed)) {
                $unknown[] = $token;
            }
        }
        return $unknown;
    }

    /**
     * @param array<int, string> $argv
     * @param list<string> $allowed
     * @throws \InvalidArgumentException when any flag is not on the allow-list
     */
    public static function assertKnown(array $argv, array $allowed): void
    {
        $unknown = self::unknownFlags($argv, $allowed);
        if ($unknown === []) {
            return;
        }
        throw new \InvalidArgumentException(
            'Unknown option(s): ' . implode(' ', $unknown) . "\n"
            . 'Accepted options: ' . ($allowed === [] ? '(none)' : implode(' ', $allowed))
        );
    }

    /** @param list<string> $allowed */
    private static function isAllowed(string $token, array $allowed): bool
    {
        foreach ($allowed as $entry) {
            if (str_ends_with($entry, '=') ? str_starts_with($token, $entry) : $token === $entry) {
                return true;
            }
        }
        return false;
    }
}
