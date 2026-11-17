<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
#[Group('mutation-unsafe')]
final class DatabaseTestCaseLeakGuardTest extends DatabaseTestCase
{
    public function testTearDownGuardPassesOpenTransaction(): void
    {
        self::assertNull(TransactionLeakGuard::failureMessage($this->readInTransaction(), false, self::class));
    }

    public function testTearDownGuardFlagsCommittedTransaction(): void
    {
        $this->db->commit();

        $message = TransactionLeakGuard::failureMessage($this->readInTransaction(), false, self::class);

        self::assertNotNull($message);
        self::assertStringContainsString('ended with no open transaction', $message);

        // Reopen so tearDown rolls back cleanly.
        $this->db->begin_transaction();
    }
}
