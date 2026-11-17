<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

/**
 * Picks the database a DB integration test connects to.
 *
 * Infection runs mutants on 4 threads and exports TEST_TOKEN=1..4 to each mutant
 * process. Both mutation workflows set INFECTION_PER_THREAD_DATABASE=1 on their
 * Infection step and clone the seeded database into <DB_NAME>_t1 .. <DB_NAME>_t4,
 * so each thread writes to its own copy. Every other run (local, tests.yml, the
 * coverage step) leaves the flag unset and connects to DB_NAME unchanged.
 */
final class DatabaseNameResolver
{
    private const TOKEN_PATTERN = '/^[0-9]{1,2}$/D';

    public static function resolve(string $baseName, string|false $perThread, string|false $token): string
    {
        if ($perThread === false || $perThread === '') {
            return $baseName;
        }
        if ($token === false || preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw new \RuntimeException(sprintf(
                'INFECTION_PER_THREAD_DATABASE is set but TEST_TOKEN is %s; expected a thread index from 1 to 99.',
                $token === false ? 'unset' : var_export($token, true),
            ));
        }
        return $baseName . '_t' . $token;
    }

    public static function fromEnvironment(string $baseName): string
    {
        return self::resolve($baseName, getenv('INFECTION_PER_THREAD_DATABASE'), getenv('TEST_TOKEN'));
    }
}
