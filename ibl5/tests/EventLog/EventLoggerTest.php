<?php

declare(strict_types=1);

namespace Tests\EventLog;

use EventLog\EventLogger;
use EventLog\EventLogRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventLoggerTest extends TestCase
{
    private \mysqli $fakeMysqli;

    protected function setUp(): void
    {
        EventLogger::reset();
        // A stub mysqli — arm() stores the reference; flush() with injected repo never uses it.
        $this->fakeMysqli = self::createStub(\mysqli::class);
    }

    protected function tearDown(): void
    {
        EventLogger::reset();
    }

    public function testFlushWithoutArmDoesNothing(): void
    {
        $repo = $this->createMock(EventLogRepository::class);
        $repo->expects($this->never())->method('updateOutcome');

        EventLogger::flush($repo);
    }

    public function testFlushPassesArmedIdAndAction(): void
    {
        $repo = $this->createMock(EventLogRepository::class);
        $repo->expects($this->once())
             ->method('updateOutcome')
             ->with(42, self::anything(), 'trade_submitted');

        EventLogger::arm(42, $this->fakeMysqli);
        EventLogger::setAction('trade_submitted');
        EventLogger::flush($repo);
    }

    public function testSecondFlushIsNoOp(): void
    {
        $repo = $this->createMock(EventLogRepository::class);
        $repo->expects($this->once())->method('updateOutcome');

        EventLogger::arm(1, $this->fakeMysqli);
        EventLogger::flush($repo);
        EventLogger::flush($repo);
    }

    public function testFlushWithUnavailableConnectionIsSilent(): void
    {
        EventLogger::arm(1, $this->fakeMysqli);
        // reset to simulate the "connection gone" path (pendingDb will be nulled).
        EventLogger::reset();
        EventLogger::arm(1, $this->fakeMysqli);

        // Provide a repo that throws to simulate a closed-connection scenario.
        $repo = self::createStub(EventLogRepository::class);
        $repo->method('updateOutcome')->willThrowException(new \Exception('Connection closed'));

        // Must not throw.
        EventLogger::flush($repo);
        $this->expectNotToPerformAssertions();
    }

    public function testFlushSwallowsRepositoryException(): void
    {
        $repo = self::createStub(EventLogRepository::class);
        $repo->method('updateOutcome')->willThrowException(new \RuntimeException('DB error'));

        EventLogger::arm(1, $this->fakeMysqli);
        EventLogger::flush($repo);  // must not throw

        // State is reset in finally — a second flush must be a no-op.
        $repo2 = $this->createMock(EventLogRepository::class);
        $repo2->expects($this->never())->method('updateOutcome');
        EventLogger::flush($repo2);
    }

    public function testSetActionTruncatesToColumnWidth(): void
    {
        $repo = $this->createMock(EventLogRepository::class);
        $repo->expects($this->once())
             ->method('updateOutcome')
             ->with(self::anything(), self::anything(), self::callback(
                 static fn (mixed $action): bool => is_string($action) && strlen($action) === 64
             ));

        EventLogger::arm(1, $this->fakeMysqli);
        EventLogger::setAction(str_repeat('a', 100));
        EventLogger::flush($repo);
    }

    public function testSetActionLastWriteWins(): void
    {
        $repo = $this->createMock(EventLogRepository::class);
        $repo->expects($this->once())
             ->method('updateOutcome')
             ->with(self::anything(), self::anything(), 'second');

        EventLogger::arm(1, $this->fakeMysqli);
        EventLogger::setAction('first');
        EventLogger::setAction('second');
        EventLogger::flush($repo);
    }

    /** @return array<string, array{int|bool, int|null}> */
    public static function normalizeStatusProvider(): array
    {
        return [
            '200 passes through'    => [200, 200],
            '100 inclusive low'     => [100, 100],
            '599 inclusive high'    => [599, 599],
            '99 below range → null' => [99, null],
            '600 above range → null'=> [600, null],
            '0 → null'              => [0, null],
            '-1 → null'             => [-1, null],
            'false (CLI) → null'    => [false, null],
        ];
    }

    #[DataProvider('normalizeStatusProvider')]
    public function testNormalizeStatusBoundaries(int|bool $input, ?int $expected): void
    {
        self::assertSame($expected, EventLogger::normalizeStatus($input));
    }
}
