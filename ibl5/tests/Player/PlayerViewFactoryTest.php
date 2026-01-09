<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\Views\PlayerViewFactory;
use Player\PlayerRepository;
use Player\PlayerStatsRepository;
use Services\CommonMysqliRepository;
use Player\Views\PlayerOverviewView;
use Player\Views\PlayerSimStatsView;
use Player\Views\PlayerRegularSeasonTotalsView;
use Player\Views\PlayerRegularSeasonAveragesView;
use Player\Views\PlayerPlayoffTotalsView;
use Player\Views\PlayerPlayoffAveragesView;
use Player\Views\PlayerHeatTotalsView;
use Player\Views\PlayerHeatAveragesView;
use Player\Views\PlayerOlympicTotalsView;
use Player\Views\PlayerOlympicAveragesView;
use Player\Views\PlayerRatingsAndSalaryView;
use Player\Views\PlayerAwardsAndNewsView;
use Player\Views\PlayerOneOnOneView;

/**
 * PlayerViewFactoryTest - Tests for the PlayerViewFactory class
 * 
 * Verifies that the factory creates the correct view instances for each page type.
 */
class PlayerViewFactoryTest extends TestCase
{
    /** @var \mysqli&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli $mockDb;
    
    /** @var PlayerRepository&\PHPUnit\Framework\MockObject\MockObject */
    private PlayerRepository $mockRepository;
    
    /** @var PlayerStatsRepository&\PHPUnit\Framework\MockObject\MockObject */
    private PlayerStatsRepository $mockStatsRepository;
    
    /** @var CommonMysqliRepository&\PHPUnit\Framework\MockObject\MockObject */
    private CommonMysqliRepository $mockCommonRepository;
    
    private PlayerViewFactory $factory;

    protected function setUp(): void
    {
        // Create mock database connection
        $this->mockDb = $this->createMock(\mysqli::class);
        
        // Create mock repositories
        $this->mockRepository = $this->createMock(PlayerRepository::class);
        $this->mockStatsRepository = $this->createMock(PlayerStatsRepository::class);
        $this->mockCommonRepository = $this->createMock(CommonMysqliRepository::class);
        
        // Create factory with mocked dependencies including CommonMysqliRepository
        $this->factory = new PlayerViewFactory(
            $this->mockRepository,
            $this->mockStatsRepository,
            $this->mockCommonRepository
        );
    }

    public function testCreateViewReturnsOverviewViewForOverviewPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::OVERVIEW);
        
        $this->assertInstanceOf(PlayerOverviewView::class, $view);
    }

    public function testCreateViewReturnsSimStatsViewForSimStatsPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::SIM_STATS);
        
        $this->assertInstanceOf(PlayerSimStatsView::class, $view);
    }

    public function testCreateViewReturnsRegularSeasonTotalsViewForRegularSeasonTotalsPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::REGULAR_SEASON_TOTALS);
        
        $this->assertInstanceOf(PlayerRegularSeasonTotalsView::class, $view);
    }

    public function testCreateViewReturnsRegularSeasonAveragesViewForRegularSeasonAveragesPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::REGULAR_SEASON_AVERAGES);
        
        $this->assertInstanceOf(PlayerRegularSeasonAveragesView::class, $view);
    }

    public function testCreateViewReturnsPlayoffTotalsViewForPlayoffTotalsPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::PLAYOFF_TOTALS);
        
        $this->assertInstanceOf(PlayerPlayoffTotalsView::class, $view);
    }

    public function testCreateViewReturnsPlayoffAveragesViewForPlayoffAveragesPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::PLAYOFF_AVERAGES);
        
        $this->assertInstanceOf(PlayerPlayoffAveragesView::class, $view);
    }

    public function testCreateViewReturnsHeatTotalsViewForHeatTotalsPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::HEAT_TOTALS);
        
        $this->assertInstanceOf(PlayerHeatTotalsView::class, $view);
    }

    public function testCreateViewReturnsHeatAveragesViewForHeatAveragesPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::HEAT_AVERAGES);
        
        $this->assertInstanceOf(PlayerHeatAveragesView::class, $view);
    }

    public function testCreateViewReturnsOlympicTotalsViewForOlympicTotalsPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::OLYMPIC_TOTALS);
        
        $this->assertInstanceOf(PlayerOlympicTotalsView::class, $view);
    }

    public function testCreateViewReturnsOlympicAveragesViewForOlympicAveragesPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::OLYMPIC_AVERAGES);
        
        $this->assertInstanceOf(PlayerOlympicAveragesView::class, $view);
    }

    public function testCreateViewReturnsRatingsAndSalaryViewForRatingsAndSalaryPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::RATINGS_AND_SALARY);
        
        $this->assertInstanceOf(PlayerRatingsAndSalaryView::class, $view);
    }

    public function testCreateViewReturnsAwardsAndNewsViewForAwardsAndNewsPageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::AWARDS_AND_NEWS);
        
        $this->assertInstanceOf(PlayerAwardsAndNewsView::class, $view);
    }

    public function testCreateViewReturnsOneOnOneViewForOneOnOnePageType(): void
    {
        $view = $this->factory->createView(\PlayerPageType::ONE_ON_ONE);
        
        $this->assertInstanceOf(PlayerOneOnOneView::class, $view);
    }

    public function testCreateViewReturnsOverviewViewForNullPageType(): void
    {
        $view = $this->factory->createView(null);
        
        $this->assertInstanceOf(PlayerOverviewView::class, $view);
    }

    public function testCreateViewReturnsOverviewViewForUnknownPageType(): void
    {
        $view = $this->factory->createView(999);
        
        $this->assertInstanceOf(PlayerOverviewView::class, $view);
    }
}
