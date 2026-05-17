<?php

declare(strict_types=1);

namespace Tests\Bootstrap\Api;

use Bootstrap\RateLimitingBootstrap;
use PHPUnit\Framework\TestCase;

final class RateLimitingBootstrapTest extends TestCase
{
    public function testImplementsBootstrapStepInterface(): void
    {
        $step = new RateLimitingBootstrap();
        self::assertInstanceOf(\Bootstrap\Contracts\BootstrapStepInterface::class, $step);
    }
}
