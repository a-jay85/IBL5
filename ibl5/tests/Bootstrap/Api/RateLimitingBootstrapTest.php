<?php

declare(strict_types=1);

namespace Tests\Bootstrap\Api;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\RateLimitingBootstrap;
use PHPUnit\Framework\TestCase;

final class RateLimitingBootstrapTest extends TestCase
{
    public function testImplementsBootstrapStepInterface(): void
    {
        $interfaces = class_implements(RateLimitingBootstrap::class);
        self::assertContains(
            BootstrapStepInterface::class,
            $interfaces ? $interfaces : [],
        );
    }
}
