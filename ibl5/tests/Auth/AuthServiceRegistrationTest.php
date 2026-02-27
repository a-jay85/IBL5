<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AuthService registration-related methods.
 *
 * Since Delight\Auth\Auth is a final class and cannot be mocked,
 * these tests focus on the error handling, lastError state management,
 * and interface contract compliance.
 */
class AuthServiceRegistrationTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $mockMysqli = static::createStub(\mysqli::class);
        // Pass null for Auth — lazy initialization means Auth won't be created
        // unless a method that needs it is called
        $this->authService = new AuthService($mockMysqli);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function testGetLastErrorReturnsNullByDefault(): void
    {
        self::assertNull($this->authService->getLastError());
    }

    public function testImplementsAuthServiceInterface(): void
    {
        self::assertInstanceOf(\Auth\Contracts\AuthServiceInterface::class, $this->authService);
    }


    public function testHashPasswordStillWorks(): void
    {
        $hash = $this->authService->hashPassword('test-password');

        self::assertStringStartsWith('$2y$12$', $hash);
        self::assertTrue(password_verify('test-password', $hash));
    }

    public function testRegisterMethodSignatureAcceptsCallable(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'register');
        $params = $reflection->getParameters();

        self::assertCount(4, $params);
        self::assertSame('email', $params[0]->getName());
        self::assertSame('password', $params[1]->getName());
        self::assertSame('username', $params[2]->getName());
        self::assertSame('emailCallback', $params[3]->getName());
        self::assertTrue($params[3]->allowsNull());
    }

    public function testConfirmEmailMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'confirmEmail');
        $params = $reflection->getParameters();

        self::assertCount(2, $params);
        self::assertSame('selector', $params[0]->getName());
        self::assertSame('token', $params[1]->getName());
    }

    public function testResetPasswordMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'resetPassword');
        $params = $reflection->getParameters();

        self::assertCount(3, $params);
        self::assertSame('selector', $params[0]->getName());
        self::assertSame('token', $params[1]->getName());
        self::assertSame('newPassword', $params[2]->getName());
    }

    public function testForgotPasswordMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'forgotPassword');
        $params = $reflection->getParameters();

        self::assertCount(2, $params);
        self::assertSame('email', $params[0]->getName());
        self::assertSame('callback', $params[1]->getName());
    }

    public function testGetLastErrorReturnType(): void
    {
        $reflection = new \ReflectionMethod(AuthService::class, 'getLastError');
        $returnType = $reflection->getReturnType();

        self::assertNotNull($returnType);
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertTrue($returnType->allowsNull());
        self::assertSame('string', $returnType->getName());
    }
}
