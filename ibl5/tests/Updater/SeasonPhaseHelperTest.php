<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Updater\SeasonPhaseHelper;
use Season\Season;

/**
 * SeasonPhaseHelperTest - Tests for season phase utilities
 */
class SeasonPhaseHelperTest extends TestCase
{
    public function testGetMonthForHEATPhase(): void
    {
        $result = SeasonPhaseHelper::getMonthForPhase('HEAT');
        $this->assertSame(Season::IBL_HEAT_MONTH, $result);
    }

    public function testGetMonthForRegularSeasonPhase(): void
    {
        $result = SeasonPhaseHelper::getMonthForPhase('Regular Season');
        $this->assertSame(Season::IBL_REGULAR_SEASON_STARTING_MONTH, $result);
    }

    public function testGetMonthForPreseasonPhase(): void
    {
        $result = SeasonPhaseHelper::getMonthForPhase('Preseason');
        $this->assertSame(Season::IBL_PRESEASON_MONTH, $result);
    }

    public function testGetMonthForFreeAgencyPhase(): void
    {
        $result = SeasonPhaseHelper::getMonthForPhase('Free Agency');
        $this->assertSame(Season::IBL_REGULAR_SEASON_STARTING_MONTH, $result);
    }
}
