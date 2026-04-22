<?php

declare(strict_types=1);

namespace Tests\Player;

use BasketballStats\StatsFormatter;
use PHPUnit\Framework\TestCase;
use Player\Contracts\PlayerStatsRepositoryInterface;

/**
 * @covers \Player\PlayerStats
 */
class PlayerStatsTest extends TestCase
{
    private TestablePlayerStats $stats;

    protected function setUp(): void
    {
        $repo = $this->createStub(PlayerStatsRepositoryInterface::class);
        $this->stats = new TestablePlayerStats($repo);
    }

    // --- fill() tests ---

    public function testFillSetsPlayerIdentityFields(): void
    {
        $this->stats->exposedFill($this->makeCurrentPlayerRow());

        $this->assertSame(12345, $this->stats->playerID);
        $this->assertSame('LeBron James', $this->stats->name);
        $this->assertSame('SF', $this->stats->position);
        $this->assertSame(0, $this->stats->isRetired);
    }

    public function testFillComputesSeasonTotalReboundsFromOrbPlusDrb(): void
    {
        $row = $this->makeCurrentPlayerRow(['stats_orb' => 50, 'stats_drb' => 200]);
        $this->stats->exposedFill($row);

        $this->assertSame(250, $this->stats->seasonTotalRebounds);
    }

    public function testFillComputesSeasonPointsViaStatsFormatter(): void
    {
        $row = $this->makeCurrentPlayerRow([
            'stats_fgm' => 400,
            'stats_ftm' => 150,
            'stats_3gm' => 100,
        ]);
        $this->stats->exposedFill($row);

        // calculatePoints: (2 * 400) + 150 + 100 = 1050
        $this->assertSame(1050, $this->stats->seasonPoints);
    }

    public function testFillComputesPerGameAverages(): void
    {
        $row = $this->makeCurrentPlayerRow([
            'stats_gm' => 82,
            'stats_ast' => 410,
            'stats_stl' => 82,
        ]);
        $this->stats->exposedFill($row);

        $this->assertSame(
            StatsFormatter::formatPerGameAverage(410, 82),
            $this->stats->seasonAssistsPerGame,
        );
        $this->assertSame(
            StatsFormatter::formatPerGameAverage(82, 82),
            $this->stats->seasonStealsPerGame,
        );
    }

    public function testFillComputesFieldGoalPercentage(): void
    {
        $row = $this->makeCurrentPlayerRow([
            'stats_fgm' => 400,
            'stats_fga' => 800,
        ]);
        $this->stats->exposedFill($row);

        $this->assertSame('0.500', $this->stats->seasonFieldGoalPercentage);
    }

    public function testFillComputesFreeThrowPercentage(): void
    {
        $row = $this->makeCurrentPlayerRow([
            'stats_ftm' => 90,
            'stats_fta' => 100,
        ]);
        $this->stats->exposedFill($row);

        $this->assertSame('0.900', $this->stats->seasonFreeThrowPercentage);
    }

    public function testFillComputesThreePointPercentage(): void
    {
        $row = $this->makeCurrentPlayerRow([
            'stats_3gm' => 100,
            'stats_3ga' => 250,
        ]);
        $this->stats->exposedFill($row);

        $this->assertSame('0.400', $this->stats->seasonThreePointPercentage);
    }

    public function testFillHandlesZeroGamesPlayedWithoutDivisionError(): void
    {
        $row = $this->makeCurrentPlayerRow(['stats_gm' => 0]);
        $this->stats->exposedFill($row);

        $this->assertSame('0.0', $this->stats->seasonPointsPerGame);
        $this->assertSame('0.0', $this->stats->seasonAssistsPerGame);
    }

    public function testFillSetsSeasonHighs(): void
    {
        $row = $this->makeCurrentPlayerRow([
            'sh_pts' => 52,
            'sh_reb' => 18,
            'sh_ast' => 15,
            'sh_stl' => 6,
            'sh_blk' => 5,
            's_dd' => 30,
            's_td' => 3,
        ]);
        $this->stats->exposedFill($row);

        $this->assertSame(52, $this->stats->seasonHighPoints);
        $this->assertSame(18, $this->stats->seasonHighRebounds);
        $this->assertSame(15, $this->stats->seasonHighAssists);
        $this->assertSame(6, $this->stats->seasonHighSteals);
        $this->assertSame(5, $this->stats->seasonHighBlocks);
        $this->assertSame(30, $this->stats->seasonDoubleDoubles);
        $this->assertSame(3, $this->stats->seasonTripleDoubles);
    }

    public function testFillSetsCareerStats(): void
    {
        $row = $this->makeCurrentPlayerRow([
            'car_gm' => 1400,
            'car_min' => 50000,
            'car_fgm' => 10000,
            'car_fga' => 20000,
            'car_ftm' => 7000,
            'car_fta' => 8000,
            'car_tgm' => 2000,
            'car_tga' => 5000,
            'car_orb' => 1000,
            'car_drb' => 6000,
            'car_reb' => 7000,
            'car_ast' => 9000,
            'car_stl' => 2000,
            'car_to' => 3000,
            'car_blk' => 800,
            'car_pf' => 2500,
        ]);
        $this->stats->exposedFill($row);

        $this->assertSame(1400, $this->stats->careerGamesPlayed);
        $this->assertSame(50000, $this->stats->careerMinutesPlayed);
        $this->assertSame(10000, $this->stats->careerFieldGoalsMade);
        $this->assertSame(7000, $this->stats->careerTotalRebounds);
        $this->assertSame(9000, $this->stats->careerAssists);
    }

    public function testFillComputesCareerPointsViaStatsFormatter(): void
    {
        $row = $this->makeCurrentPlayerRow([
            'car_fgm' => 10000,
            'car_ftm' => 7000,
            'car_tgm' => 2000,
        ]);
        $this->stats->exposedFill($row);

        // (2 * 10000) + 7000 + 2000 = 29000
        $this->assertSame(29000, $this->stats->careerPoints);
    }

    public function testFillMissingOptionalColumnsDefaultToZero(): void
    {
        $row = ['pid' => 1, 'name' => 'Test', 'pos' => 'PG', 'retired' => 0];
        $this->stats->exposedFill($row);

        $this->assertSame(0, $this->stats->seasonGamesPlayed);
        $this->assertSame(0, $this->stats->seasonPoints);
        $this->assertSame(0, $this->stats->careerGamesPlayed);
        $this->assertSame(0, $this->stats->seasonHighPoints);
    }

    // --- fillHistorical() tests ---

    public function testFillHistoricalUsesShortColumnNames(): void
    {
        $row = $this->makeHistoricalRow();
        $this->stats->exposedFillHistorical($row);

        $this->assertSame(82, $this->stats->seasonGamesPlayed);
        $this->assertSame(3000, $this->stats->seasonMinutes);
        $this->assertSame(500, $this->stats->seasonFieldGoalsMade);
    }

    public function testFillHistoricalComputesDefensiveReboundsAsRebMinusOrb(): void
    {
        $row = $this->makeHistoricalRow(['reb' => 600, 'orb' => 100]);
        $this->stats->exposedFillHistorical($row);

        $this->assertSame(600, $this->stats->seasonTotalRebounds);
        $this->assertSame(500, $this->stats->seasonDefensiveRebounds);
    }

    public function testFillHistoricalSetsGamesStartedToZero(): void
    {
        $this->stats->exposedFillHistorical($this->makeHistoricalRow());

        $this->assertSame(0, $this->stats->seasonGamesStarted);
    }

    public function testFillHistoricalComputesPercentages(): void
    {
        $row = $this->makeHistoricalRow([
            'fgm' => 400,
            'fga' => 800,
            'ftm' => 180,
            'fta' => 200,
        ]);
        $this->stats->exposedFillHistorical($row);

        $this->assertSame('0.500', $this->stats->seasonFieldGoalPercentage);
        $this->assertSame('0.900', $this->stats->seasonFreeThrowPercentage);
    }

    public function testFillHistoricalHandlesZeroGamesPlayedWithoutDivisionError(): void
    {
        $row = $this->makeHistoricalRow(['games' => 0]);
        $this->stats->exposedFillHistorical($row);

        $this->assertSame('0.0', $this->stats->seasonPointsPerGame);
        $this->assertSame('0.0', $this->stats->seasonAssistsPerGame);
    }

    // --- fillBoxscoreStats() tests ---

    public function testFillBoxscoreStatsExtractsNameFromFixedOffset(): void
    {
        $line = $this->makeBoxscoreLine();
        $this->stats->exposedFillBoxscoreStats($line);

        $this->assertSame('LeBron James', $this->stats->name);
    }

    public function testFillBoxscoreStatsExtractsPositionFromFixedOffset(): void
    {
        $line = $this->makeBoxscoreLine();
        $this->stats->exposedFillBoxscoreStats($line);

        $this->assertSame('SF', $this->stats->position);
    }

    public function testFillBoxscoreStatsExtractsPlayerIdFromFixedOffset(): void
    {
        $line = $this->makeBoxscoreLine();
        $this->stats->exposedFillBoxscoreStats($line);

        $this->assertSame('12345', $this->stats->playerID);
    }

    public function testFillBoxscoreStatsExtractsGameStats(): void
    {
        $line = $this->makeBoxscoreLine();
        $this->stats->exposedFillBoxscoreStats($line);

        $this->assertSame('36', $this->stats->gameMinutesPlayed);
        $this->assertSame('10', $this->stats->gameFieldGoalsMade);
        $this->assertSame(' 20', $this->stats->gameFieldGoalsAttempted);
        $this->assertSame(' 5', $this->stats->gameFreeThrowsMade);
        $this->assertSame(' 6', $this->stats->gameFreeThrowsAttempted);
    }

    public function testFillBoxscoreStatsTrimsNameAndPosition(): void
    {
        // Name padded to 16 chars, position padded to 2 chars
        $line = 'Short Name      PG 99999362010 20 5 6 3 5 2 8 7 2 3 4 2';
        $this->stats->exposedFillBoxscoreStats($line);

        $this->assertSame('Short Name', $this->stats->name);
        $this->assertSame('PG', $this->stats->position);
    }

    // --- Helper methods ---

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeCurrentPlayerRow(array $overrides = []): array
    {
        return array_merge([
            'pid' => 12345,
            'name' => 'LeBron James',
            'pos' => 'SF',
            'retired' => 0,
            'stats_gs' => 80,
            'stats_gm' => 82,
            'stats_min' => 3000,
            'stats_fgm' => 800,
            'stats_fga' => 1600,
            'stats_ftm' => 400,
            'stats_fta' => 500,
            'stats_3gm' => 150,
            'stats_3ga' => 400,
            'stats_orb' => 50,
            'stats_drb' => 400,
            'stats_ast' => 600,
            'stats_stl' => 100,
            'stats_tvr' => 250,
            'stats_blk' => 50,
            'stats_pf' => 150,
            'sh_pts' => 40,
            'sh_reb' => 15,
            'sh_ast' => 12,
            'sh_stl' => 5,
            'sh_blk' => 4,
            's_dd' => 20,
            's_td' => 2,
            'sp_pts' => 35,
            'sp_reb' => 12,
            'sp_ast' => 10,
            'sp_stl' => 4,
            'sp_blk' => 3,
            'ch_pts' => 52,
            'ch_reb' => 18,
            'ch_ast' => 15,
            'ch_stl' => 6,
            'ch_blk' => 5,
            'c_dd' => 100,
            'c_td' => 15,
            'cp_pts' => 45,
            'cp_reb' => 16,
            'cp_ast' => 13,
            'cp_stl' => 5,
            'cp_blk' => 4,
            'car_gm' => 1400,
            'car_min' => 50000,
            'car_fgm' => 10000,
            'car_fga' => 20000,
            'car_ftm' => 7000,
            'car_fta' => 8000,
            'car_tgm' => 2000,
            'car_tga' => 5000,
            'car_orb' => 1000,
            'car_drb' => 6000,
            'car_reb' => 7000,
            'car_ast' => 9000,
            'car_stl' => 2000,
            'car_to' => 3000,
            'car_blk' => 800,
            'car_pf' => 2500,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeHistoricalRow(array $overrides = []): array
    {
        return array_merge([
            'games' => 82,
            'minutes' => 3000,
            'fgm' => 500,
            'fga' => 1000,
            'ftm' => 200,
            'fta' => 250,
            'tgm' => 100,
            'tga' => 300,
            'orb' => 80,
            'reb' => 500,
            'ast' => 400,
            'stl' => 120,
            'blk' => 50,
            'tvr' => 200,
            'pf' => 150,
        ], $overrides);
    }

    /**
     * Build a fixed-width boxscore info line.
     *
     * Format: name (16) | pos (2) | pid (6) | min (2) | fgm (2) | fga (3) | ftm (2) | fta (2) ...
     */
    private function makeBoxscoreLine(): string
    {
        // "LeBron James    SF 1234536" + stat columns
        return 'LeBron James    SF 123453610 20 5 6 3 5 2 8 7 2 3 4 2';
    }
}

/**
 * Testable subclass that exposes protected fill methods.
 */
class TestablePlayerStats extends \Player\PlayerStats
{
    /**
     * @param array<string, mixed> $plrRow
     */
    public function exposedFill(array $plrRow): void
    {
        $this->fill($plrRow);
    }

    /**
     * @param array<string, mixed> $plrRow
     */
    public function exposedFillHistorical(array $plrRow): void
    {
        $this->fillHistorical($plrRow);
    }

    public function exposedFillBoxscoreStats(string $playerInfoLine): void
    {
        $this->fillBoxscoreStats($playerInfoLine);
    }
}
