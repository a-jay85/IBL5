<?php

declare(strict_types=1);

namespace Tests\NextSim;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use NextSim\NextSimView;
use NextSim\Contracts\NextSimViewInterface;

/**
 * NextSimViewTest - Tests for NextSimView HTML rendering
 *
 * Note: NextSimView requires db, Season, and moduleName constructor arguments.
 *
 * @covers \NextSim\NextSimView
 */
#[AllowMockObjectsWithoutExpectations]
class NextSimViewTest extends TestCase
{
    public function testImplementsNextSimViewInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season::class);

        $view = new NextSimView($mockDb, $mockSeason, 'NextSim');

        $this->assertInstanceOf(NextSimViewInterface::class, $view);
    }

    public function testRenderReturnsString(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season::class);

        $view = new NextSimView($mockDb, $mockSeason, 'NextSim');
        $result = $view->render([], 7);

        $this->assertIsString($result);
    }

    public function testRenderContainsTitle(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season::class);

        $view = new NextSimView($mockDb, $mockSeason, 'NextSim');
        $result = $view->render([], 7);

        $this->assertStringContainsString('Next Sim', $result);
    }

    public function testRenderShowsNoGamesMessage(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season::class);

        $view = new NextSimView($mockDb, $mockSeason, 'NextSim');
        $result = $view->render([], 7);

        $this->assertStringContainsString('No games projected next sim', $result);
    }
}
