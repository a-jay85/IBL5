<?php

declare(strict_types=1);

namespace Tests\Support;

use Logging\LoggerFactory;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

trait AuditLogAssertions
{
    private TestHandler $auditTestHandler;

    private function setUpAuditLogCapture(): void
    {
        LoggerFactory::forTests();

        /** @var Logger $logger */
        $logger = LoggerFactory::getChannel('audit');

        $this->auditTestHandler = new TestHandler();
        $logger->pushHandler($this->auditTestHandler);
    }

    private function tearDownAuditLogCapture(): void
    {
        LoggerFactory::reset();
    }

    private function assertAuditLogEmitted(string $message): void
    {
        \PHPUnit\Framework\Assert::assertTrue(
            $this->auditTestHandler->hasInfoThatContains($message),
            "Expected audit log with message containing '{$message}' was not emitted"
        );
    }

    /**
     * @param array<string, mixed> $expectedContext
     */
    private function assertAuditLogContext(string $message, array $expectedContext): void
    {
        $records = $this->auditTestHandler->getRecords();
        $matched = false;

        foreach ($records as $record) {
            if ($record->message === $message) {
                foreach ($expectedContext as $key => $value) {
                    \PHPUnit\Framework\Assert::assertArrayHasKey(
                        $key,
                        $record->context,
                        "Audit log '{$message}' is missing context key '{$key}'"
                    );
                    \PHPUnit\Framework\Assert::assertSame(
                        $value,
                        $record->context[$key],
                        "Audit log '{$message}' context key '{$key}' has unexpected value"
                    );
                }
                $matched = true;
                break;
            }
        }

        \PHPUnit\Framework\Assert::assertTrue($matched, "No audit log with message '{$message}' found");
    }

    private function assertAuditLogNotEmitted(string $message): void
    {
        \PHPUnit\Framework\Assert::assertFalse(
            $this->auditTestHandler->hasInfoThatContains($message),
            "Audit log with message containing '{$message}' should NOT have been emitted"
        );
    }

    /**
     * Assert a specific context key is NOT present in the audit log for the given message.
     */
    private function assertAuditLogContextMissing(string $message, string $forbiddenKey): void
    {
        $records = $this->auditTestHandler->getRecords();
        foreach ($records as $record) {
            if ($record->message === $message) {
                \PHPUnit\Framework\Assert::assertArrayNotHasKey(
                    $forbiddenKey,
                    $record->context,
                    "Audit log '{$message}' must NOT contain context key '{$forbiddenKey}'"
                );
            }
        }
    }
}
