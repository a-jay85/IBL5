<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\ConfigBootstrap;
use PHPUnit\Framework\TestCase;
use Season\SharedSeason;
use Tests\WideUnit\Mocks\MockDatabase;

final class SeasonRegistrationTest extends TestCase
{
    public function testRegisterSharedServicesRegistersExpectedKeys(): void
    {
        $container = new \Bootstrap\Container();

        $reflection = new \ReflectionMethod(ConfigBootstrap::class, 'registerSharedServices');
        $bootstrap = new ConfigBootstrap(__DIR__ . '/../../', false);
        $reflection->invoke($bootstrap, $container);

        self::assertTrue($container->has('season'));
        self::assertTrue($container->has('mysqli_db'));
        self::assertTrue($container->has('logger.app'));
        self::assertTrue($container->has('logger.audit'));
        self::assertTrue($container->has('logger.db'));
        self::assertTrue($container->has('logger.discord'));
        self::assertTrue($container->has('logger.draft'));
        self::assertTrue($container->has('logger.admin'));
        self::assertTrue($container->has('logger.perf'));
    }

    public function testSeasonEntryDelegatesToSharedSeason(): void
    {
        $container = new \Bootstrap\Container();
        $reflection = new \ReflectionMethod(ConfigBootstrap::class, 'registerSharedServices');
        $reflection->invoke(new ConfigBootstrap(__DIR__ . '/../../', false), $container);

        $db = new MockDatabase();
        $db->setMockData([]);
        $hadGlobal = array_key_exists('mysqli_db', $GLOBALS);
        $previous = $GLOBALS['mysqli_db'] ?? null;
        $GLOBALS['mysqli_db'] = $db;
        SharedSeason::enable();

        try {
            self::assertSame(SharedSeason::get($db), $container->get('season'));
        } finally {
            SharedSeason::disable();
            if ($hadGlobal) {
                $GLOBALS['mysqli_db'] = $previous;
            } else {
                unset($GLOBALS['mysqli_db']);
            }
        }
    }
}
