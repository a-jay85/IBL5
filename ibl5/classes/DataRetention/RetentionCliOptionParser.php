<?php

declare(strict_types=1);

namespace DataRetention;

/**
 * Strict argv parser for the retention CLI scripts.
 *
 * PHP's getopt() accepts `--days 30`, ignores unknown flags, and stops at the first
 * positional, so it cannot fail loudly. This parser rejects all three.
 */
final class RetentionCliOptionParser
{
    /**
     * @param list<string> $args argv without the script name
     * @param array<string, int|null> $spec flag => max for an `--flag=N` integer flag, null for a boolean flag
     * @param list<string> $required
     * @return array<string, int|true>
     * @throws \InvalidArgumentException
     */
    public static function parse(array $args, array $spec, array $required = []): array
    {
        $parsed = [];

        foreach ($args as $token) {
            if (!str_starts_with($token, '--')) {
                throw new \InvalidArgumentException("unexpected argument '" . $token . "'");
            }

            $equalsPos = strpos($token, '=');
            $name = $equalsPos === false ? $token : substr($token, 0, $equalsPos);
            $value = $equalsPos === false ? null : substr($token, $equalsPos + 1);

            if (!array_key_exists($name, $spec)) {
                throw new \InvalidArgumentException("unknown flag '" . $name . "'");
            }
            if (array_key_exists($name, $parsed)) {
                throw new \InvalidArgumentException("duplicate flag '" . $name . "'");
            }

            $max = $spec[$name];
            if ($max === null) {
                if ($value !== null) {
                    throw new \InvalidArgumentException("flag '" . $name . "' takes no value");
                }
                $parsed[$name] = true;
                continue;
            }

            if ($value === null) {
                throw new \InvalidArgumentException("flag '" . $name . "' requires a value: use " . $name . '=N');
            }
            $maxDigits = (string) $max;
            // Compare lengths before casting so a 30-digit string cannot overflow.
            if (
                preg_match('/^[1-9][0-9]*$/', $value) !== 1
                || strlen($value) > strlen($maxDigits)
                || (strlen($value) === strlen($maxDigits) && strcmp($value, $maxDigits) > 0)
            ) {
                throw new \InvalidArgumentException(
                    "flag '" . $name . "' needs a positive integer up to " . $max . ", got '" . $value . "'"
                );
            }
            $parsed[$name] = (int) $value;
        }

        foreach ($required as $name) {
            if (!array_key_exists($name, $parsed)) {
                throw new \InvalidArgumentException("missing required flag '" . $name . "'");
            }
        }

        return $parsed;
    }
}
