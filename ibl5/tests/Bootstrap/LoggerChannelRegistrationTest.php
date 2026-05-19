<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\ConfigBootstrap;
use Bootstrap\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LoggerChannelRegistrationTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $reflection = new \ReflectionMethod(ConfigBootstrap::class, 'registerSharedServices');
        $bootstrap = new ConfigBootstrap(__DIR__ . '/../../', false);
        $reflection->invoke($bootstrap, $this->container);
    }

    #[DataProvider('channelProvider')]
    public function testLoggerChannelResolvesToLoggerInterface(string $channel): void
    {
        $logger = $this->container->get("logger.{$channel}");
        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    #[DataProvider('channelProvider')]
    public function testResolvingSameChannelReturnsSameInstance(string $channel): void
    {
        $first = $this->container->get("logger.{$channel}");
        $second = $this->container->get("logger.{$channel}");
        self::assertSame($first, $second);
    }

    /** @return array<string, array{string}> */
    public static function channelProvider(): array
    {
        return [
            'app' => ['app'],
            'audit' => ['audit'],
            'db' => ['db'],
            'discord' => ['discord'],
            'draft' => ['draft'],
            'admin' => ['admin'],
            'perf' => ['perf'],
        ];
    }
}
