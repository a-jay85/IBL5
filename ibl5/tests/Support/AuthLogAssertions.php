<?php

declare(strict_types=1);

namespace Tests\Support;

use Logging\LoggerFactory;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

trait AuthLogAssertions
{
    private TestHandler $authTestHandler;

    private function setUpAuthLogCapture(): void
    {
        LoggerFactory::forTests();

        /** @var Logger $logger */
        $logger = LoggerFactory::getChannel('auth');

        $this->authTestHandler = new TestHandler();
        $logger->pushHandler($this->authTestHandler);
    }

    private function tearDownAuthLogCapture(): void
    {
        LoggerFactory::reset();
    }

    private function assertAuthLogEmitted(string $message): void
    {
        \PHPUnit\Framework\Assert::assertTrue(
            $this->authTestHandler->hasInfoThatContains($message),
            "Expected auth log with message containing '{$message}' was not emitted"
        );
    }

    private function assertAuthWarningLogEmitted(string $message): void
    {
        \PHPUnit\Framework\Assert::assertTrue(
            $this->authTestHandler->hasWarningThatContains($message),
            "Expected auth WARNING log with message containing '{$message}' was not emitted"
        );
    }

    /**
     * @param array<string, mixed> $expectedContext
     */
    private function assertAuthLogContext(string $message, array $expectedContext): void
    {
        $records = $this->authTestHandler->getRecords();
        $matched = false;

        foreach ($records as $record) {
            if ($record->message === $message) {
                foreach ($expectedContext as $key => $value) {
                    \PHPUnit\Framework\Assert::assertArrayHasKey(
                        $key,
                        $record->context,
                        "Auth log '{$message}' is missing context key '{$key}'"
                    );
                    \PHPUnit\Framework\Assert::assertSame(
                        $value,
                        $record->context[$key],
                        "Auth log '{$message}' context key '{$key}' has unexpected value"
                    );
                }
                $matched = true;
                break;
            }
        }

        \PHPUnit\Framework\Assert::assertTrue($matched, "No auth log with message '{$message}' found");
    }

    /**
     * @param array<string, mixed> $expectedContext
     */
    private function assertAuthWarningLogContext(string $message, array $expectedContext): void
    {
        $records = $this->authTestHandler->getRecords();
        $matched = false;

        foreach ($records as $record) {
            if ($record->message === $message && $record->level === \Monolog\Level::Warning) {
                foreach ($expectedContext as $key => $value) {
                    \PHPUnit\Framework\Assert::assertArrayHasKey(
                        $key,
                        $record->context,
                        "Auth warning log '{$message}' is missing context key '{$key}'"
                    );
                    \PHPUnit\Framework\Assert::assertSame(
                        $value,
                        $record->context[$key],
                        "Auth warning log '{$message}' context key '{$key}' has unexpected value"
                    );
                }
                $matched = true;
                break;
            }
        }

        \PHPUnit\Framework\Assert::assertTrue($matched, "No auth warning log with message '{$message}' found");
    }

    private function assertAuthLogNotEmitted(string $message): void
    {
        $hasInfo = $this->authTestHandler->hasInfoThatContains($message);
        $hasWarning = $this->authTestHandler->hasWarningThatContains($message);
        \PHPUnit\Framework\Assert::assertFalse(
            $hasInfo || $hasWarning,
            "Auth log with message containing '{$message}' should NOT have been emitted"
        );
    }

    private function assertAuthLogContextMissing(string $message, string $forbiddenKey): void
    {
        $records = $this->authTestHandler->getRecords();
        foreach ($records as $record) {
            if ($record->message === $message) {
                \PHPUnit\Framework\Assert::assertArrayNotHasKey(
                    $forbiddenKey,
                    $record->context,
                    "Auth log '{$message}' must NOT contain context key '{$forbiddenKey}'"
                );
            }
        }
    }
}
