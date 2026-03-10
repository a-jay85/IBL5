<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testSetAndGetRawValue(): void
    {
        $this->container->set('db_host', 'localhost');

        self::assertSame('localhost', $this->container->get('db_host'));
    }

    public function testHasReturnsTrueForRegisteredEntry(): void
    {
        $this->container->set('key', 'value');

        self::assertTrue($this->container->has('key'));
    }

    public function testHasReturnsFalseForMissingEntry(): void
    {
        self::assertFalse($this->container->has('missing'));
    }

    public function testGetThrowsForMissingEntry(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Container entry not found: missing');

        $this->container->get('missing');
    }

    public function testFactoryClosureIsCalledLazily(): void
    {
        $callCount = 0;

        $this->container->set('service', static function () use (&$callCount): string {
            $callCount++;
            return 'created';
        });

        // Factory not yet called
        self::assertSame(0, $callCount);

        $result = $this->container->get('service');

        self::assertSame('created', $result);
        self::assertSame(1, $callCount);
    }

    public function testFactoryResultIsCached(): void
    {
        $callCount = 0;

        $this->container->set('service', static function () use (&$callCount): string {
            $callCount++;
            return 'singleton';
        });

        $this->container->get('service');
        $this->container->get('service');
        $this->container->get('service');

        self::assertSame(1, $callCount);
    }

    public function testFactoryReceivesContainerInstance(): void
    {
        $this->container->set('dep', 'dependency-value');
        $this->container->set('service', static function (Container $c): string {
            /** @var string $dep */
            $dep = $c->get('dep');
            return 'using-' . $dep;
        });

        self::assertSame('using-dependency-value', $this->container->get('service'));
    }

    public function testSetOverwritesPreviousEntry(): void
    {
        $this->container->set('key', 'old');
        $this->container->set('key', 'new');

        self::assertSame('new', $this->container->get('key'));
    }

    public function testSetOverwritesCachedFactory(): void
    {
        $this->container->set('key', static fn (): string => 'from-factory');
        $this->container->get('key'); // resolve and cache

        $this->container->set('key', 'raw-value');
        self::assertSame('raw-value', $this->container->get('key'));
    }

    public function testNullValueCanBeStored(): void
    {
        $this->container->set('nullable', null);

        self::assertTrue($this->container->has('nullable'));
        self::assertNull($this->container->get('nullable'));
    }

    public function testIntegerValueCanBeStored(): void
    {
        $this->container->set('port', 3306);

        self::assertSame(3306, $this->container->get('port'));
    }

    public function testFactoryReturningNullIsCached(): void
    {
        $callCount = 0;

        $this->container->set('nullable-factory', static function () use (&$callCount): mixed {
            $callCount++;
            return null;
        });

        $this->container->get('nullable-factory');
        $this->container->get('nullable-factory');

        self::assertSame(1, $callCount);
    }

    public function testFactoryReturningFalseIsCached(): void
    {
        $callCount = 0;

        $this->container->set('false-factory', static function () use (&$callCount): bool {
            $callCount++;
            return false;
        });

        $this->container->get('false-factory');
        $this->container->get('false-factory');

        self::assertSame(1, $callCount);
    }
}
