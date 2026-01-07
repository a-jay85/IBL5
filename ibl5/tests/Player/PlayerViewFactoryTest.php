<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\PlayerViewFactory;
use Player\Player;
use Player\PlayerStats;
use Player\Contracts\PlayerPageViewInterface;

/**
 * Tests for PlayerViewFactory
 */
class PlayerViewFactoryTest extends TestCase
{
    /** @var \mysqli&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli $mockDb;

    /** @var Player&\PHPUnit\Framework\MockObject\MockObject */
    private Player $mockPlayer;

    /** @var PlayerStats&\PHPUnit\Framework\MockObject\MockObject */
    private PlayerStats $mockPlayerStats;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(\mysqli::class);
        $this->mockPlayer = $this->createMock(Player::class);
        $this->mockPlayerStats = $this->createMock(PlayerStats::class);
    }

    public function testCreateOverviewViewForNullPageType(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(null);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateOverviewViewForOverviewPageType(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::OVERVIEW);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateSimStatsView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::SIM_STATS);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateRegularSeasonTotalsView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::REGULAR_SEASON_TOTALS);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateRegularSeasonAveragesView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::REGULAR_SEASON_AVERAGES);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreatePlayoffTotalsView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::PLAYOFF_TOTALS);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreatePlayoffAveragesView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::PLAYOFF_AVERAGES);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateHeatTotalsView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::HEAT_TOTALS);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateHeatAveragesView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::HEAT_AVERAGES);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateOlympicTotalsView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::OLYMPIC_TOTALS);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateOlympicAveragesView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::OLYMPIC_AVERAGES);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateRatingsAndSalaryView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::RATINGS_AND_SALARY);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testCreateAwardsAndNewsView(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::AWARDS_AND_NEWS);
        
        $this->assertInstanceOf(PlayerPageViewInterface::class, $view);
    }

    public function testOneOnOneReturnsNullAsNotYetMigrated(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(\PlayerPageType::ONE_ON_ONE);
        
        $this->assertNull($view, 'ONE_ON_ONE should return null as it is not yet migrated');
    }

    public function testHasMigratedViewReturnsTrueForMigratedViews(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $this->assertTrue($factory->hasMigratedView(\PlayerPageType::OVERVIEW));
        $this->assertTrue($factory->hasMigratedView(\PlayerPageType::SIM_STATS));
        $this->assertTrue($factory->hasMigratedView(\PlayerPageType::REGULAR_SEASON_TOTALS));
    }

    public function testHasMigratedViewReturnsFalseForOneOnOne(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $this->assertFalse($factory->hasMigratedView(\PlayerPageType::ONE_ON_ONE));
    }

    public function testInvalidPageTypeReturnsNull(): void
    {
        $factory = new PlayerViewFactory($this->mockDb, $this->mockPlayer, $this->mockPlayerStats);
        
        $view = $factory->create(9999);
        
        $this->assertNull($view, 'Invalid page type should return null');
    }
}
