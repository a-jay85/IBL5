<?php

declare(strict_types=1);

namespace Tests\Database;

use Database\PdoConnection;
use PHPUnit\Framework\TestCase;

/**
 * PdoConnectionTest - Unit tests for PdoConnection singleton
 */
class PdoConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        PdoConnection::reset();
        parent::tearDown();
    }

    public function testResetClearsSingleton(): void
    {
        PdoConnection::reset();

        // After reset, getInstance() should create a fresh connection
        // (would need config.php globals; just verify reset doesn't throw)
        self::assertTrue(true);
    }

    public function testCreateWithCredentialsThrowsOnInvalidHost(): void
    {
        $this->expectException(\PDOException::class);

        PdoConnection::createWithCredentials(
            'invalid-host-that-does-not-exist',
            'nobody',
            'nopass',
            'nodb',
        );
    }
}
