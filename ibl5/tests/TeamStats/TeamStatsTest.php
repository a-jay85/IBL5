<?php

declare(strict_types=1);

namespace Tests\TeamStats;

use BasketballStats\StatsFormatter;
use PHPUnit\Framework\TestCase;
use TeamOffDefStats\TeamOffDefStatsRepository;

/**
 * @covers \TeamStats
 */
class TeamStatsTest extends TestCase
{
    private TestableTeamStats $teamStats;

    protected function setUp(): void
    {
        $repo = $this->createStub(TeamOffDefStatsRepository::class);
        $this->teamStats = new TestableTeamStats($repo);
    }

    // --- Constructor defaults ---

    public function testConstructorDefaultsIntPropertiesToZero(): void
    {
        $this->assertSame(0, $this->teamStats->seasonOffenseGamesPlayed);
        $this->assertSame(0, $this->teamStats->seasonOffenseTotalFieldGoalsMade);
        $this->assertSame(0, $this->teamStats->seasonOffenseTotalPoints);
        $this->assertSame(0, $this->teamStats->seasonDefenseGamesPlayed);
    }

    public function testConstructorDefaultsStringPropertiesToFormattedZeros(): void
    {
        $this->assertSame('0.0', $this->teamStats->seasonOffensePointsPerGame);
        $this->assertSame('0.000', $this->teamStats->seasonOffenseFieldGoalPercentage);
        $this->assertSame('0.0', $this->teamStats->seasonDefensePointsPerGame);
        $this->assertSame('0.000', $this->teamStats->seasonDefenseFieldGoalPercentage);
    }

    // --- fillOffenseTotals() ---

    public function testFillOffenseSetsRawTotalsFromRow(): void
    {
        $this->teamStats->exposedFillOffense($this->makeStatsRow());

        $this->assertSame(82, $this->teamStats->seasonOffenseGamesPlayed);
        $this->assertSame(3000, $this->teamStats->seasonOffenseTotalFieldGoalsMade);
        $this->assertSame(6000, $this->teamStats->seasonOffenseTotalFieldGoalsAttempted);
        $this->assertSame(400, $this->teamStats->seasonOffenseTotalAssists);
    }

    public function testFillOffenseComputesPointsViaStatsFormatter(): void
    {
        $row = $this->makeStatsRow(['fgm' => 3000, 'ftm' => 1500, 'tgm' => 800]);
        $this->teamStats->exposedFillOffense($row);

        // (2 * 3000) + 1500 + 800 = 8300
        $this->assertSame(8300, $this->teamStats->seasonOffenseTotalPoints);
    }

    public function testFillOffenseComputesPerGameAverages(): void
    {
        $row = $this->makeStatsRow(['games' => 82, 'ast' => 1640]);
        $this->teamStats->exposedFillOffense($row);

        $this->assertSame(
            StatsFormatter::formatPerGameAverage(1640, 82),
            $this->teamStats->seasonOffenseAssistsPerGame,
        );
    }

    public function testFillOffenseComputesShootingPercentages(): void
    {
        $row = $this->makeStatsRow(['fgm' => 3000, 'fga' => 6000]);
        $this->teamStats->exposedFillOffense($row);

        $this->assertSame('0.500', $this->teamStats->seasonOffenseFieldGoalPercentage);
    }

    public function testFillOffenseHandlesZeroGamesPlayedWithoutError(): void
    {
        $row = $this->makeStatsRow(['games' => 0]);
        $this->teamStats->exposedFillOffense($row);

        $this->assertSame('0.0', $this->teamStats->seasonOffensePointsPerGame);
    }

    public function testFillOffenseDefensiveReboundsCalculatedCorrectly(): void
    {
        $row = $this->makeStatsRow(['orb' => 800, 'reb' => 3400]);
        $this->teamStats->exposedFillOffense($row);

        $this->assertSame(3400, $this->teamStats->seasonOffenseTotalRebounds);
        $this->assertSame(800, $this->teamStats->seasonOffenseTotalOffensiveRebounds);
        $this->assertSame(2600, $this->teamStats->seasonOffenseTotalDefensiveRebounds);
    }

    // --- fillDefenseTotals() ---

    public function testFillDefenseSetsRawTotalsFromRow(): void
    {
        $this->teamStats->exposedFillDefense($this->makeStatsRow());

        $this->assertSame(82, $this->teamStats->seasonDefenseGamesPlayed);
        $this->assertSame(3000, $this->teamStats->seasonDefenseTotalFieldGoalsMade);
    }

    public function testFillDefenseComputesPointsViaStatsFormatter(): void
    {
        $row = $this->makeStatsRow(['fgm' => 2800, 'ftm' => 1200, 'tgm' => 600]);
        $this->teamStats->exposedFillDefense($row);

        // (2 * 2800) + 1200 + 600 = 7400
        $this->assertSame(7400, $this->teamStats->seasonDefenseTotalPoints);
    }

    public function testFillDefenseComputesShootingPercentages(): void
    {
        $row = $this->makeStatsRow(['ftm' => 1500, 'fta' => 2000]);
        $this->teamStats->exposedFillDefense($row);

        $this->assertSame('0.750', $this->teamStats->seasonDefenseFreeThrowPercentage);
    }

    public function testFillDefenseHandlesZeroGamesPlayedWithoutError(): void
    {
        $row = $this->makeStatsRow(['games' => 0]);
        $this->teamStats->exposedFillDefense($row);

        $this->assertSame('0.0', $this->teamStats->seasonDefensePointsPerGame);
    }

    public function testFillDefenseDefensiveReboundsCalculatedCorrectly(): void
    {
        $row = $this->makeStatsRow(['orb' => 700, 'reb' => 3200]);
        $this->teamStats->exposedFillDefense($row);

        $this->assertSame(3200, $this->teamStats->seasonDefenseTotalRebounds);
        $this->assertSame(700, $this->teamStats->seasonDefenseTotalOffensiveRebounds);
        $this->assertSame(2500, $this->teamStats->seasonDefenseTotalDefensiveRebounds);
    }

    public function testLoadByTeamNameDoesNothingWhenRepoReturnsNull(): void
    {
        $repo = $this->createStub(TeamOffDefStatsRepository::class);
        $repo->method('getTeamBothStats')->willReturn(null);

        $stats = new TestableTeamStats($repo);
        $stats->exposedLoadByTeamName('Nonexistent', 2025);

        $this->assertSame(0, $stats->seasonOffenseGamesPlayed);
        $this->assertSame(0, $stats->seasonDefenseGamesPlayed);
    }

    /**
     * @param array<string, int> $overrides
     * @return array<string, int>
     */
    private function makeStatsRow(array $overrides = []): array
    {
        return array_merge([
            'games' => 82,
            'fgm' => 3000,
            'fga' => 6000,
            'ftm' => 1500,
            'fta' => 2000,
            'tgm' => 800,
            'tga' => 2200,
            'orb' => 900,
            'reb' => 3400,
            'ast' => 400,
            'stl' => 600,
            'tvr' => 1100,
            'blk' => 350,
            'pf' => 1600,
        ], $overrides);
    }
}

/**
 * Testable subclass that exposes protected fill methods.
 */
class TestableTeamStats extends \TeamStats
{
    /**
     * @param array<string, int> $row
     */
    public function exposedFillOffense(array $row): void
    {
        $this->fillOffenseTotals($row);
    }

    /**
     * @param array<string, int> $row
     */
    public function exposedFillDefense(array $row): void
    {
        $this->fillDefenseTotals($row);
    }

    public function exposedLoadByTeamName(string $teamName, int $seasonYear): void
    {
        $this->loadByTeamName($teamName, $seasonYear);
    }
}
