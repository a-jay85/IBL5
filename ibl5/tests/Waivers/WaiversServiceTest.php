<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\WaiversService;

class WaiversServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $interfaces = class_implements(WaiversService::class);
        self::assertContains(
            WaiversServiceInterface::class,
            $interfaces ? $interfaces : [],
        );
    }
}
