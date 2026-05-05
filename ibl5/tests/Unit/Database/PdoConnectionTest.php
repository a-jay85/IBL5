<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Database\PdoConnection;
use PHPUnit\Framework\TestCase;

class PdoConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        PdoConnection::reset();
    }

    protected function tearDown(): void
    {
        PdoConnection::reset();
    }

    public function testSetInstanceStoresPdo(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        PdoConnection::setInstance($pdo);
        self::assertSame($pdo, PdoConnection::getInstance());
    }

    public function testGetInstanceReturnsSameInstanceOnRepeatedCalls(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        PdoConnection::setInstance($pdo);
        $first = PdoConnection::getInstance();
        $second = PdoConnection::getInstance();
        self::assertSame($first, $second);
    }

    public function testResetClearsInstance(): void
    {
        $pdoA = new \PDO('sqlite::memory:');
        $pdoB = new \PDO('sqlite::memory:');
        PdoConnection::setInstance($pdoA);
        self::assertSame($pdoA, PdoConnection::getInstance());

        PdoConnection::reset();
        PdoConnection::setInstance($pdoB);
        self::assertSame($pdoB, PdoConnection::getInstance());
    }

    public function testGetInstanceWithoutSetThrowsWhenDbUnreachable(): void
    {
        $GLOBALS['dbhost'] = '';
        $GLOBALS['dbuname'] = '';
        $GLOBALS['dbpass'] = '';
        $GLOBALS['dbname'] = '';

        try {
            $this->expectException(\PDOException::class);
            PdoConnection::getInstance();
        } finally {
            unset($GLOBALS['dbhost'], $GLOBALS['dbuname'], $GLOBALS['dbpass'], $GLOBALS['dbname']);
        }
    }

    public function testSetInstanceOverridesPrevious(): void
    {
        $pdoA = new \PDO('sqlite::memory:');
        $pdoB = new \PDO('sqlite::memory:');
        PdoConnection::setInstance($pdoA);
        PdoConnection::setInstance($pdoB);
        self::assertSame($pdoB, PdoConnection::getInstance());
    }
}
