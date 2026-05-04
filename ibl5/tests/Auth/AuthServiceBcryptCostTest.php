<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServiceBcryptCostTest extends TestCase
{
    public function testProductionBcryptCostIs12(): void
    {
        self::assertSame(12, AuthService::BCRYPT_COST_PROD);
    }
}
