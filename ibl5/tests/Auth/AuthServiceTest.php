<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthService;
use Auth\Contracts\AuthServiceInterface;
use PHPUnit\Framework\TestCase;

/**
 * AuthServiceTest - Unit tests for AuthService
 *
 * Tests that do not require a database connection (password hashing, interface compliance).
 * Auth-dependent methods (login, register, etc.) require integration tests with a real DB.
 */
class AuthServiceTest extends TestCase
{
    public function testHashPasswordProducesBcryptHash(): void
    {
        $authService = $this->createAuthServiceForHashTests();
        $hash = $authService->hashPassword('testpassword');

        self::assertStringStartsWith('$2y$', $hash);
        self::assertGreaterThanOrEqual(60, strlen($hash));
    }

    public function testHashPasswordProducesVerifiableHash(): void
    {
        $authService = $this->createAuthServiceForHashTests();
        $password = 'my-secure-password';
        $hash = $authService->hashPassword($password);

        self::assertTrue(password_verify($password, $hash));
    }

    public function testHashPasswordWithDifferentPasswordsFails(): void
    {
        $authService = $this->createAuthServiceForHashTests();
        $hash = $authService->hashPassword('correct-password');

        self::assertFalse(password_verify('wrong-password', $hash));
    }

    public function testHashPasswordUsesCost12(): void
    {
        $authService = $this->createAuthServiceForHashTests();
        $hash = $authService->hashPassword('test');

        // bcrypt hash format: $2y$12$...
        self::assertStringStartsWith('$2y$12$', $hash);
    }

    public function testHashPasswordProducesUniqueHashes(): void
    {
        $authService = $this->createAuthServiceForHashTests();
        $hash1 = $authService->hashPassword('same-password');
        $hash2 = $authService->hashPassword('same-password');

        // bcrypt includes a random salt, so two hashes of the same password differ
        self::assertNotSame($hash1, $hash2);
        // But both verify against the original password
        self::assertTrue(password_verify('same-password', $hash1));
        self::assertTrue(password_verify('same-password', $hash2));
    }

    public function testImplementsAuthServiceInterface(): void
    {
        $authService = $this->createAuthServiceForHashTests();
        self::assertInstanceOf(AuthServiceInterface::class, $authService);
    }

    public function testGetLastErrorReturnsNullInitially(): void
    {
        $authService = $this->createAuthServiceForHashTests();
        self::assertNull($authService->getLastError());
    }

    /**
     * Create AuthService with a real PDO + Auth for password hash tests.
     * Uses an in-memory SQLite database to avoid needing MySQL.
     */
    private function createAuthServiceForHashTests(): AuthService
    {
        $mockMysqli = self::createStub(\mysqli::class);

        // Create an in-memory SQLite database with the required auth tables
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create the auth tables that delight-im/auth needs
        $pdo->exec('CREATE TABLE IF NOT EXISTS auth_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(249) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL DEFAULT \'\',
            username VARCHAR(100) DEFAULT NULL,
            status TINYINT NOT NULL DEFAULT 0,
            verified TINYINT NOT NULL DEFAULT 0,
            resettable TINYINT NOT NULL DEFAULT 1,
            roles_mask INTEGER NOT NULL DEFAULT 0,
            registered INTEGER NOT NULL,
            last_login INTEGER DEFAULT NULL,
            force_logout INTEGER NOT NULL DEFAULT 0
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS auth_users_confirmations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            email VARCHAR(249) NOT NULL,
            selector VARCHAR(16) NOT NULL UNIQUE,
            token VARCHAR(255) NOT NULL,
            expires INTEGER NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS auth_users_remembered (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user INTEGER NOT NULL,
            selector VARCHAR(24) NOT NULL UNIQUE,
            token VARCHAR(255) NOT NULL,
            expires INTEGER NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS auth_users_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user INTEGER NOT NULL,
            selector VARCHAR(20) NOT NULL UNIQUE,
            token VARCHAR(255) NOT NULL,
            expires INTEGER NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS auth_users_throttling (
            bucket VARCHAR(44) PRIMARY KEY,
            tokens REAL NOT NULL,
            replenished_at INTEGER NOT NULL,
            expires_at INTEGER NOT NULL
        )');

        // Start session if needed (Auth constructor may need it)
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }

        $auth = new \Delight\Auth\Auth($pdo, null, 'auth_', false);

        return new AuthService($mockMysqli, $auth);
    }
}
