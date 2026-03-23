<?php

declare(strict_types=1);

namespace Tests\Logging;

use Logging\Contracts\LoggerFactoryInterface;
use Logging\LoggerFactory;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LoggerFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        LoggerFactory::reset();
    }

    public function testChannelReturnsLoggerInterface(): void
    {
        $factory = LoggerFactory::forTests();

        $this->assertInstanceOf(LoggerInterface::class, $factory->channel('db'));
    }

    public function testSameChannelReturnsSameInstance(): void
    {
        $factory = LoggerFactory::forTests();

        $logger1 = $factory->channel('discord');
        $logger2 = $factory->channel('discord');

        $this->assertSame($logger1, $logger2);
    }

    public function testDifferentChannelsReturnDifferentInstances(): void
    {
        $factory = LoggerFactory::forTests();

        $loggerA = $factory->channel('db');
        $loggerB = $factory->channel('trade');

        $this->assertNotSame($loggerA, $loggerB);
    }

    public function testForTestsReturnsNullLogger(): void
    {
        $factory = LoggerFactory::forTests();

        $logger = $factory->channel('test');
        $this->assertInstanceOf(Logger::class, $logger);

        // Verify NullHandler is present (no file I/O)
        /** @var Logger $monologLogger */
        $monologLogger = $logger;
        $handlers = $monologLogger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(NullHandler::class, $handlers[0]);
    }

    public function testFromConfigLoadsDefaults(): void
    {
        // fromConfig() should succeed even without a config file
        $factory = LoggerFactory::fromConfig();

        $logger = $factory->channel('app');
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testResetClearsSingleton(): void
    {
        $factory1 = LoggerFactory::forTests();
        $logger1 = LoggerFactory::getChannel('test');

        LoggerFactory::reset();

        $factory2 = LoggerFactory::forTests();
        $logger2 = LoggerFactory::getChannel('test');

        // After reset, the singleton is a new instance, so channels are new
        $this->assertNotSame($logger1, $logger2);
    }

    public function testGetChannelFallsBackToNullHandler(): void
    {
        LoggerFactory::reset();

        // getChannel() should not throw even if no factory has been initialized
        $logger = LoggerFactory::getChannel('fallback');
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testImplementsLoggerFactoryInterface(): void
    {
        $factory = LoggerFactory::forTests();

        $this->assertInstanceOf(LoggerFactoryInterface::class, $factory);
    }

    public function testChannelNameSetOnLogger(): void
    {
        $factory = LoggerFactory::forTests();

        /** @var Logger $logger */
        $logger = $factory->channel('discord');

        $this->assertSame('discord', $logger->getName());
    }

    public function testFromConfigAttachesProcessors(): void
    {
        $factory = LoggerFactory::fromConfig();

        /** @var Logger $logger */
        $logger = $factory->channel('app');

        $this->assertCount(3, $logger->getProcessors());
    }

    public function testForTestsHasNoProcessors(): void
    {
        $factory = LoggerFactory::forTests();

        /** @var Logger $logger */
        $logger = $factory->channel('test');

        $this->assertCount(0, $logger->getProcessors());
    }
}
