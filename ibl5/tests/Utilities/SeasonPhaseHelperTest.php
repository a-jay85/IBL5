<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\SeasonPhaseHelper;

/**
 * SeasonPhaseHelperTest - Tests for season phase utilities
 */
class SeasonPhaseHelperTest extends TestCase
{
    public function testGetMonthForHEATPhase(): void
    {
        $result = SeasonPhaseHelper::getMonthForPhase('HEAT');
        $this->assertEquals(\Season::IBL_HEAT_MONTH, $result);
    }

    public function testGetMonthForRegularSeasonPhase(): void
    {
        $result = SeasonPhaseHelper::getMonthForPhase('Regular Season');
        $this->assertEquals(\Season::IBL_REGULAR_SEASON_STARTING_MONTH, $result);
    }

    public function testGetMonthForPreseasonPhase(): void
    {
        $result = SeasonPhaseHelper::getMonthForPhase('Preseason');
        $this->assertEquals(\Season::IBL_REGULAR_SEASON_STARTING_MONTH, $result);
    }

    public function testGetMonthForFreeAgencyPhase(): void
    {
        $result = SeasonPhaseHelper::getMonthForPhase('Free Agency');
        $this->assertEquals(\Season::IBL_REGULAR_SEASON_STARTING_MONTH, $result);
    }

    public function testIsRegularSeasonPhaseForRegularSeason(): void
    {
        $this->assertTrue(SeasonPhaseHelper::isRegularSeasonPhase('Regular Season'));
    }

    public function testIsRegularSeasonPhaseForPreseason(): void
    {
        $this->assertTrue(SeasonPhaseHelper::isRegularSeasonPhase('Preseason'));
    }

    public function testIsRegularSeasonPhaseReturnsFalseForHEAT(): void
    {
        $this->assertFalse(SeasonPhaseHelper::isRegularSeasonPhase('HEAT'));
    }

    public function testIsHeatPhaseForHEAT(): void
    {
        $this->assertTrue(SeasonPhaseHelper::isHeatPhase('HEAT'));
    }

    public function testIsHeatPhaseReturnsFalseForRegularSeason(): void
    {
        $this->assertFalse(SeasonPhaseHelper::isHeatPhase('Regular Season'));
    }
}
