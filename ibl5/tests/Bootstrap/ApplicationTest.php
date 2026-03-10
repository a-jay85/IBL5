<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\Application;
use Bootstrap\Container;
use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testBootExecutesStepsInOrder(): void
    {
        $order = [];

        $step1 = new class ($order) implements BootstrapStepInterface {
            /** @var list<string> */
            private array $order;

            /** @param list<string> $order */
            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            public function boot(ContainerInterface $container): void
            {
                $this->order[] = 'step1';
                $container->set('step1', true);
            }
        };

        $step2 = new class ($order) implements BootstrapStepInterface {
            /** @var list<string> */
            private array $order;

            /** @param list<string> $order */
            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            public function boot(ContainerInterface $container): void
            {
                $this->order[] = 'step2';
                $container->set('step2', true);
            }
        };

        $app = new Application();
        $app->addStep($step1);
        $app->addStep($step2);
        $app->boot();

        self::assertSame(['step1', 'step2'], $order);
    }

    public function testStepsPopulateContainer(): void
    {
        $step = new class implements BootstrapStepInterface {
            public function boot(ContainerInterface $container): void
            {
                $container->set('testKey', 'testValue');
            }
        };

        $app = new Application();
        $app->addStep($step);
        $app->boot();

        self::assertSame('testValue', $app->getContainer()->get('testKey'));
    }

    public function testStepsCanPopulateGlobals(): void
    {
        $step = new class implements BootstrapStepInterface {
            public function boot(ContainerInterface $container): void
            {
                $GLOBALS['bootstrap_test_global'] = 'from_bootstrap';
            }
        };

        $app = new Application();
        $app->addStep($step);
        $app->boot();

        self::assertSame('from_bootstrap', $GLOBALS['bootstrap_test_global']);
        unset($GLOBALS['bootstrap_test_global']);
    }

    public function testAcceptsCustomContainer(): void
    {
        $container = new Container();
        $container->set('pre_existing', 'yes');

        $app = new Application($container);

        self::assertSame($container, $app->getContainer());
        self::assertSame('yes', $app->getContainer()->get('pre_existing'));
    }

    public function testBootWithNoStepsDoesNothing(): void
    {
        $app = new Application();
        $app->boot();

        // No exception — empty boot is valid
        self::assertInstanceOf(ContainerInterface::class, $app->getContainer());
    }
}
