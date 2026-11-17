<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

/**
 * Decides whether a DatabaseTestCase test left its transaction open.
 *
 * A test that commits its transaction persists writes. Infection reruns the
 * same test once per mutant in one per-thread database, so the repeat run
 * fails and the failure counts as a fake kill. Such classes carry
 * #[Group('mutation-unsafe')], which phpunit-mutation.xml excludes.
 */
final class TransactionLeakGuard
{
    public const GROUP = 'mutation-unsafe';

    public static function failureMessage(int|string|null $inTransaction, bool $mutationUnsafe, string $testClass): ?string
    {
        if ($mutationUnsafe) {
            return null;
        }
        if ($inTransaction === null) {
            return $testClass . ': could not read @@in_transaction after the test, so DatabaseTestCase cannot tell whether the test committed its transaction.';
        }
        if ((int) $inTransaction === 1) {
            return null;
        }
        return $testClass . " ended with no open transaction: something committed the DatabaseTestCase transaction, so its writes persist. Tag the class #[Group('mutation-unsafe')]. Infection reuses one database per thread and would fail the repeat run.";
    }

    /** @param class-string $testClass */
    public static function isMutationUnsafe(string $testClass): bool
    {
        foreach ((new \ReflectionClass($testClass))->getAttributes(\PHPUnit\Framework\Attributes\Group::class) as $attribute) {
            if (($attribute->getArguments()[0] ?? null) === self::GROUP) {
                return true;
            }
        }
        return false;
    }
}
