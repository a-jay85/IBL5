<?php

declare(strict_types=1);

namespace Tests\UI\Contracts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InterfaceContractsTest extends TestCase
{
    /** @return iterable<string, array{class-string, class-string}> */
    public static function classToInterfaceProvider(): iterable
    {
        yield 'AlertRenderer'         => [\UI\AlertRenderer::class,                  \UI\Contracts\AlertRendererInterface::class];
        yield 'DebugOutput'           => [\UI\DebugOutput::class,                    \UI\Contracts\DebugOutputInterface::class];
        yield 'TableStyles'           => [\UI\TableStyles::class,                    \UI\Contracts\TableStylesInterface::class];
        yield 'TeamCellHelper'        => [\UI\TeamCellHelper::class,                 \UI\Contracts\TeamCellHelperInterface::class];
        yield 'TableViewDropdown'     => [\UI\Components\TableViewDropdown::class,   \UI\Contracts\TableViewDropdownInterface::class];
        yield 'TableViewSwitcher'     => [\UI\Components\TableViewSwitcher::class,   \UI\Contracts\TableViewSwitcherInterface::class];
        yield 'TooltipLabel'          => [\UI\Components\TooltipLabel::class,        \UI\Contracts\TooltipLabelInterface::class];
        yield 'TablesContracts'       => [\UI\Tables\Contracts::class,               \UI\Contracts\ContractsTableInterface::class];
        yield 'PlayerRowTransformer'  => [\UI\Tables\PlayerRowTransformer::class,    \UI\Contracts\PlayerRowTransformerInterface::class];
        yield 'Ratings'               => [\UI\Tables\Ratings::class,                 \UI\Contracts\RatingsInterface::class];
    }

    /**
     * @param class-string $class
     * @param class-string $interface
     */
    #[DataProvider('classToInterfaceProvider')]
    public function testClassImplementsInterface(string $class, string $interface): void
    {
        $implements = class_implements($class);
        self::assertNotFalse($implements, "{$class} must be autoloadable");
        self::assertContains(
            $interface,
            $implements,
            "{$class} must implement {$interface}",
        );
    }
}
