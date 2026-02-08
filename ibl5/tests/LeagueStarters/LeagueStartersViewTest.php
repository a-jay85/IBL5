<?php

declare(strict_types=1);

namespace Tests\LeagueStarters;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use LeagueStarters\LeagueStartersView;
use LeagueStarters\Contracts\LeagueStartersViewInterface;

/**
 * LeagueStartersViewTest - Tests for LeagueStartersView HTML rendering
 *
 * Note: LeagueStartersView requires db, Season, and moduleName constructor arguments,
 * and calls UI::ratings() which requires a database connection.
 * Full render tests require integration testing with a database.
 *
 * @covers \LeagueStarters\LeagueStartersView
 */
#[AllowMockObjectsWithoutExpectations]
class LeagueStartersViewTest extends TestCase
{
    public function testImplementsLeagueStartersViewInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season::class);

        $view = new LeagueStartersView($mockDb, $mockSeason, 'LeagueStarters');

        $this->assertInstanceOf(LeagueStartersViewInterface::class, $view);
    }
}
