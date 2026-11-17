<?php

declare(strict_types=1);

namespace Tests\SimRecap;

use Logging\LoggerFactory;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SimRecap\PublishEnqueuer;
use SimRecap\SimPublishProgressRepository;

/**
 * @covers \SimRecap\PublishEnqueuer
 */
final class PublishEnqueuerTest extends TestCase
{
    private const SECRET_MESSAGE = 'connect to http://secret-host.invalid:9999/simRecapPublish failed';

    protected function tearDown(): void
    {
        LoggerFactory::reset();
    }

    public function testUnpublishablePhaseSkipsEnqueueAndLogsNothing(): void
    {
        $progress = $this->createMock(SimPublishProgressRepository::class);
        $progress->expects($this->never())->method('enqueue');
        $pokeCalls = 0;
        $handler = new TestHandler();
        $enqueuer = new PublishEnqueuer(
            $progress,
            static function () use (&$pokeCalls): void {
                $pokeCalls++;
            },
            new Logger('sim-recap', [$handler]),
        );

        $result = $enqueuer->enqueue(42, 'Playoffs');

        $this->assertFalse($result);
        $this->assertSame(0, $pokeCalls);
        $this->assertSame([], $handler->getRecords());
    }

    public function testPokeFailureStillReturnsTrueAfterEnqueue(): void
    {
        $progress = $this->createMock(SimPublishProgressRepository::class);
        $progress->expects($this->once())->method('enqueue')->with(42);
        $enqueuer = new PublishEnqueuer($progress, self::throwingCallable(), new Logger('sim-recap', [new TestHandler()]));

        $this->assertTrue($enqueuer->enqueue(42, 'Regular Season'));
    }

    public function testPokeFailureLogsAtErrorLevel(): void
    {
        $handler = new TestHandler();
        $enqueuer = new PublishEnqueuer($this->progressStub(), self::throwingCallable(), new Logger('sim-recap', [$handler]));

        $enqueuer->enqueue(42, 'Regular Season');

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(Level::Error, $records[0]->level);
        $this->assertSame('sim-recap publish: poke failed', $records[0]->message);
        $this->assertSame(42, $records[0]->context['sim']);
        $this->assertSame(\RuntimeException::class, $records[0]->context['exception_class']);
    }

    public function testPokeFailureLogOmitsExceptionText(): void
    {
        $handler = new TestHandler();
        $enqueuer = new PublishEnqueuer($this->progressStub(), self::throwingCallable(), new Logger('sim-recap', [$handler]));

        $enqueuer->enqueue(42, 'Regular Season');

        $this->assertRecordsOmitSecret($handler);
    }

    public function testDefaultLoggerUsesLoggerFactoryChannel(): void
    {
        $handler = new TestHandler();
        LoggerFactory::forTesting($handler);
        $enqueuer = new PublishEnqueuer($this->progressStub(), self::throwingCallable());

        $enqueuer->enqueue(42, 'Regular Season');

        $this->assertCount(1, $handler->getRecords());
        $this->assertSame(Level::Error, $handler->getRecords()[0]->level);
    }

    public function testGuardedEnqueueLogsErrorAndDoesNotRethrow(): void
    {
        $handler = new TestHandler();

        PublishEnqueuer::guardedEnqueue(42, self::throwingEnqueue(), new Logger('sim-recap', [$handler]));

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(Level::Error, $records[0]->level);
        $this->assertSame('simRecapQueue: publish enqueue failed', $records[0]->message);
        $this->assertSame(42, $records[0]->context['sim']);
    }

    public function testGuardedEnqueueLogOmitsExceptionText(): void
    {
        $handler = new TestHandler();

        PublishEnqueuer::guardedEnqueue(42, self::throwingEnqueue(), new Logger('sim-recap', [$handler]));

        $this->assertRecordsOmitSecret($handler);
    }

    public function testGuardedEnqueueSuccessLogsNothing(): void
    {
        $handler = new TestHandler();

        PublishEnqueuer::guardedEnqueue(42, static fn (): bool => true, new Logger('sim-recap', [$handler]));

        $this->assertSame([], $handler->getRecords());
    }

    public function testBrokenLoggerNeverBreaksEnqueue(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $logger->method('error')->willThrowException(new \RuntimeException('log handler down'));

        $enqueuer = new PublishEnqueuer($this->progressStub(), self::throwingCallable(), $logger);
        $this->assertTrue($enqueuer->enqueue(42, 'Regular Season'));

        PublishEnqueuer::guardedEnqueue(42, self::throwingEnqueue(), $logger);
    }

    private function progressStub(): SimPublishProgressRepository
    {
        return self::createStub(SimPublishProgressRepository::class);
    }

    /**
     * @return callable(): void
     */
    private static function throwingCallable(): callable
    {
        return static function (): void {
            throw new \RuntimeException(self::SECRET_MESSAGE);
        };
    }

    /**
     * @return callable(): bool
     */
    private static function throwingEnqueue(): callable
    {
        return static function (): bool {
            throw new \RuntimeException(self::SECRET_MESSAGE);
        };
    }

    private function assertRecordsOmitSecret(TestHandler $handler): void
    {
        $encoded = json_encode(array_map(static fn ($record) => $record->toArray(), $handler->getRecords()));
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('secret-host', $encoded);
        $this->assertStringNotContainsString('simRecapPublish', $encoded);
    }
}
