<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class TransactionLeakGuardTest extends TestCase
{
    public function testOpenTransactionPasses(): void
    {
        self::assertNull(TransactionLeakGuard::failureMessage(1, false, 'X'));
    }

    public function testCommittedTransactionFailsUntaggedTest(): void
    {
        $message = TransactionLeakGuard::failureMessage(0, false, 'X');

        self::assertNotNull($message);
        self::assertStringContainsString('ended with no open transaction', $message);
    }

    public function testCommittedTransactionPassesTaggedTest(): void
    {
        self::assertNull(TransactionLeakGuard::failureMessage(0, true, 'X'));
    }

    public function testUnreadableInTransactionFails(): void
    {
        $message = TransactionLeakGuard::failureMessage(null, false, 'X');

        self::assertNotNull($message);
        self::assertStringContainsString('@@in_transaction', $message);
    }

    public function testIsMutationUnsafeTrueForTaggedClass(): void
    {
        $tagged = new #[Group('mutation-unsafe')] class {
        };

        self::assertTrue(TransactionLeakGuard::isMutationUnsafe($tagged::class));
    }

    public function testIsMutationUnsafeFalseForNearMissGroup(): void
    {
        $nearMiss = new #[Group('mutation-unsafe-later')] class {
        };

        self::assertFalse(TransactionLeakGuard::isMutationUnsafe($nearMiss::class));
    }
}
