<?php

declare(strict_types=1);

namespace Tests\Logging;

use Logging\DiscordWebhookHandler;
use Logging\LoggerFactory;
use Logging\PiiRedactionProcessor;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class LoggerFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        LoggerFactory::reset();
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

        /** @var Logger $monologLogger */
        $monologLogger = $logger;
        $handlers = $monologLogger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(NullHandler::class, $handlers[0]);
    }

    public function testResetClearsSingleton(): void
    {
        $factory1 = LoggerFactory::forTests();
        $logger1 = LoggerFactory::getChannel('test');

        LoggerFactory::reset();

        $factory2 = LoggerFactory::forTests();
        $logger2 = LoggerFactory::getChannel('test');

        $this->assertNotSame($logger1, $logger2);
    }

    public function testGetChannelFallsBackToNullHandler(): void
    {
        LoggerFactory::reset();

        $logger = LoggerFactory::getChannel('fallback');

        /** @var \Monolog\Logger $monologLogger */
        $monologLogger = $logger;
        $this->assertCount(1, $monologLogger->getHandlers());
        $this->assertInstanceOf(NullHandler::class, $monologLogger->getHandlers()[0]);
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

        $this->assertCount(4, $logger->getProcessors());
    }

    public function testForTestsHasNoProcessors(): void
    {
        $factory = LoggerFactory::forTests();

        /** @var Logger $logger */
        $logger = $factory->channel('test');

        $this->assertCount(0, $logger->getProcessors());
    }

    public function testFromConfigSetsSlowQueryThreshold(): void
    {
        LoggerFactory::fromConfig();

        $this->assertSame(200, LoggerFactory::getSlowQueryThresholdMs());
    }

    public function testForTestsDisablesSlowQueryLogging(): void
    {
        LoggerFactory::forTests();

        $this->assertSame(0, LoggerFactory::getSlowQueryThresholdMs());
    }

    public function testForTestingUsesProvidedHandler(): void
    {
        $handler = new TestHandler();
        $factory = LoggerFactory::forTesting($handler);

        /** @var Logger $logger */
        $logger = $factory->channel('test');

        $handlers = $logger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertSame($handler, $handlers[0]);
    }

    // --- Per-channel retention ---

    public function testAuditChannelGetsDedicatedHandler(): void
    {
        $factory = LoggerFactory::fromConfig();

        /** @var Logger $logger */
        $logger = $factory->channel('audit');

        $handlers = $logger->getHandlers();
        $rotatingHandlers = array_filter(
            $handlers,
            static fn ($h): bool => $h instanceof RotatingFileHandler,
        );

        // audit should have its own RotatingFileHandler + the shared one
        $this->assertGreaterThanOrEqual(2, count($rotatingHandlers));
    }

    public function testAdminChannelGetsDedicatedHandler(): void
    {
        $factory = LoggerFactory::fromConfig();

        /** @var Logger $logger */
        $logger = $factory->channel('admin');

        $handlers = $logger->getHandlers();
        $rotatingHandlers = array_filter(
            $handlers,
            static fn ($h): bool => $h instanceof RotatingFileHandler,
        );

        $this->assertGreaterThanOrEqual(2, count($rotatingHandlers));
    }

    public function testRegularChannelUsesSharedHandler(): void
    {
        $factory = LoggerFactory::fromConfig();

        /** @var Logger $auditLogger */
        $auditLogger = $factory->channel('audit');

        /** @var Logger $appLogger */
        $appLogger = $factory->channel('app');

        $auditRotating = array_filter(
            $auditLogger->getHandlers(),
            static fn ($h): bool => $h instanceof RotatingFileHandler,
        );

        $appRotating = array_filter(
            $appLogger->getHandlers(),
            static fn ($h): bool => $h instanceof RotatingFileHandler,
        );

        // audit has 2 RotatingFileHandlers (dedicated + shared), app has 1 (shared only)
        $this->assertGreaterThan(count($appRotating), count($auditRotating));
    }

    // --- Discord handler ---

    public function testDiscordHandlerNotAddedWhenUrlNull(): void
    {
        $factory = LoggerFactory::fromConfig();

        /** @var Logger $logger */
        $logger = $factory->channel('app');

        $discordHandlers = array_filter(
            $logger->getHandlers(),
            static fn ($h): bool => $h instanceof DiscordWebhookHandler,
        );

        $this->assertCount(0, $discordHandlers);
    }

    public function testFromConfigIncludesPiiRedactionProcessor(): void
    {
        $factory = LoggerFactory::fromConfig();

        /** @var Logger $logger */
        $logger = $factory->channel('app');

        $piiProcessors = array_filter(
            $logger->getProcessors(),
            static fn ($p): bool => $p instanceof PiiRedactionProcessor,
        );

        $this->assertCount(1, $piiProcessors);
    }
}
