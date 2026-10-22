<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\Container;
use Bootstrap\SharedSeasonBootstrap;
use PHPUnit\Framework\TestCase;
use Season\SharedSeason;

final class SharedSeasonBootstrapTest extends TestCase
{
    protected function setUp(): void
    {
        SharedSeason::disable();
    }

    protected function tearDown(): void
    {
        SharedSeason::disable();
    }

    public function testBootArmsOnWebSapi(): void
    {
        (new SharedSeasonBootstrap('fpm-fcgi'))->boot(new Container());

        self::assertTrue(SharedSeason::isEnabled());
    }

    public function testBootIsNoOpOnCliSapi(): void
    {
        (new SharedSeasonBootstrap('cli'))->boot(new Container());

        self::assertFalse(SharedSeason::isEnabled());
    }

    public function testBootArmsOnCliServerSapi(): void
    {
        (new SharedSeasonBootstrap('cli-server'))->boot(new Container());

        self::assertTrue(SharedSeason::isEnabled());
    }
}
