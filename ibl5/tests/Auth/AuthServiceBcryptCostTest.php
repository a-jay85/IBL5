<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServiceBcryptCostTest extends TestCase
{
    public function testProductionBcryptCostIs12(): void
    {
        $reflection = new \ReflectionClass(AuthService::class);
        $constant = $reflection->getConstant('BCRYPT_COST_PROD');

        self::assertSame(12, $constant);
    }
}
