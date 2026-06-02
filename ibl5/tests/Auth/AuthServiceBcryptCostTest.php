<?php

declare(strict_types=1);

namespace Tests\Auth;

use Auth\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServiceBcryptCostTest extends TestCase
{
    public function testProductionBcryptCostProducesExpectedHashPrefix(): void
    {
        $hash = password_hash('test', PASSWORD_BCRYPT, ['cost' => AuthService::BCRYPT_COST_PROD]);
        // A bcrypt hash with cost 12 always starts with $2y$12$
        $this->assertStringStartsWith('$2y$12$', $hash);
    }
}
