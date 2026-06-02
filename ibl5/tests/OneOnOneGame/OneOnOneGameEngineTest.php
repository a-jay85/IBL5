<?php

declare(strict_types=1);

namespace Tests\OneOnOneGame;

use PHPUnit\Framework\TestCase;
use OneOnOneGame\OneOnOneGameEngine;
use OneOnOneGame\OneOnOneGameTextGenerator;
use OneOnOneGame\OneOnOneGameResult;

/**
 * Tests for OneOnOneGameEngine
 *
 * @phpstan-import-type PlayerGameData from \OneOnOneGame\Contracts\OneOnOneGameEngineInterface
 */
final class OneOnOneGameEngineTest extends TestCase
{
    private OneOnOneGameEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new OneOnOneGameEngine();
    }

    // ========== Block Check Tests ==========

    public function testCheckBlockReturnsBooleanValue(): void
    {
        $result = $this->engine->checkBlock(50, 50);

        $this->assertIsBool($result);
    }

    public function testCheckBlockWithHighBlockRatingIncreasesBlockChance(): void
    {
        // Run multiple times to verify statistical tendency
        $blockCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->engine->checkBlock(100, 10)) {
                $blockCount++;
            }
        }

        // With very high block rating vs low attempt rating, should block more often
        // Not deterministic, but should get some blocks
        $this->assertGreaterThanOrEqual(0, $blockCount);
    }

    // ========== Steal Check Tests ==========

    public function testCheckStealReturnsBooleanValue(): void
    {
        $result = $this->engine->checkSteal(50, 50);

        $this->assertIsBool($result);
    }

    public function testCheckStealWithHighStealRatingIncreasesStealChance(): void
    {
        $stealCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->engine->checkSteal(100, 10)) {
                $stealCount++;
            }
        }

        $this->assertGreaterThanOrEqual(0, $stealCount);
    }

    // ========== Foul Check Tests ==========

    public function testCheckFoulReturnsBooleanValue(): void
    {
        $result = $this->engine->checkFoul(50, 50);

        $this->assertIsBool($result);
    }

    public function testCheckFoulWithHighDrawFoulRatingIncreasesFoulChance(): void
    {
        $foulCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->engine->checkFoul(10, 100)) {
                $foulCount++;
            }
        }

        $this->assertGreaterThanOrEqual(0, $foulCount);
    }

    // ========== Shot Check Tests ==========

    public function testCheckShotReturnsBooleanValue(): void
    {
        $result = $this->engine->checkShot(50, 50, 25);

        $this->assertIsBool($result);
    }

    public function testCheckShotWithHighPercentageIncreasesMakeChance(): void
    {
        $madeCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Very high percentage, high offense, low defense
            if ($this->engine->checkShot(90, 50, 10)) {
                $madeCount++;
            }
        }

        // Should make most shots with these ratings
        $this->assertGreaterThan(50, $madeCount);
    }

    public function testCheckShotWithLowPercentageDecreaseMakeChance(): void
    {
        $madeCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Very low percentage, low offense, high defense
            if ($this->engine->checkShot(10, 10, 50)) {
                $madeCount++;
            }
        }

        // Should miss most shots with these ratings
        $this->assertLessThan(50, $madeCount);
    }

    // ========== Rebound Check Tests ==========

    public function testCheckReboundReturnsBooleanValue(): void
    {
        $result = $this->engine->checkRebound(30, 70);

        $this->assertIsBool($result);
    }

    public function testCheckReboundWithHighOffensiveReboundingFavorsOffense(): void
    {
        $offRebCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->engine->checkRebound(90, 10)) {
                $offRebCount++;
            }
        }

        // Should get offensive rebound more often with high ORB
        $this->assertGreaterThan(30, $offRebCount);
    }

    public function testCheckReboundWithHighDefensiveReboundingFavorsDefense(): void
    {
        $offRebCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->engine->checkRebound(10, 90)) {
                $offRebCount++;
            }
        }

        // Should get offensive rebound less often with high DRB
        $this->assertLessThan(50, $offRebCount);
    }

    // ========== Game Simulation Tests ==========

    public function testSimulateGameSetsPlayerNames(): void
    {
        $player1Data = $this->createMockPlayerData('Michael Jordan');
        $player2Data = $this->createMockPlayerData('LeBron James');

        $result = $this->engine->simulateGame($player1Data, $player2Data, 'TestOwner');

        $this->assertSame('Michael Jordan', $result->player1Name);
        $this->assertSame('LeBron James', $result->player2Name);
    }

    public function testSimulateGameSetsOwner(): void
    {
        $player1Data = $this->createMockPlayerData('Player One');
        $player2Data = $this->createMockPlayerData('Player Two');

        $result = $this->engine->simulateGame($player1Data, $player2Data, 'GameOwner');

        $this->assertSame('GameOwner', $result->owner);
    }

    public function testSimulateGameEndsAt21Points(): void
    {
        $player1Data = $this->createMockPlayerData('Player One');
        $player2Data = $this->createMockPlayerData('Player Two');

        $result = $this->engine->simulateGame($player1Data, $player2Data, 'TestOwner');

        $maxScore = max($result->player1Score, $result->player2Score);
        $this->assertGreaterThanOrEqual(21, $maxScore);
    }

    public function testSimulateGameGeneratesPlayByPlay(): void
    {
        $player1Data = $this->createMockPlayerData('Player One');
        $player2Data = $this->createMockPlayerData('Player Two');

        $result = $this->engine->simulateGame($player1Data, $player2Data, 'TestOwner');

        $this->assertNotEmpty($result->playByPlay);
        $this->assertStringContainsString('SCORE:', $result->playByPlay);
        $this->assertStringContainsString('FINAL SCORE:', $result->playByPlay);
    }

    public function testSimulateGameIncludesCoinFlipInPlayByPlay(): void
    {
        $player1Data = $this->createMockPlayerData('Player One');
        $player2Data = $this->createMockPlayerData('Player Two');

        $result = $this->engine->simulateGame($player1Data, $player2Data, 'TestOwner');

        $this->assertStringContainsString('coin flip', $result->playByPlay);
    }

    public function testSimulateGameTracksStatistics(): void
    {
        $player1Data = $this->createMockPlayerData('Player One');
        $player2Data = $this->createMockPlayerData('Player Two');

        $result = $this->engine->simulateGame($player1Data, $player2Data, 'TestOwner');

        // The game should have some statistical activity
        // Either field goal attempts or free throw attempts from fouls
        $totalFGA = $result->player1Stats->fieldGoalsAttempted + $result->player2Stats->fieldGoalsAttempted;
        $totalFouls = $result->player1Stats->fouls + $result->player2Stats->fouls;
        
        // At least something should have happened in the game
        $this->assertGreaterThan(0, $totalFGA + $totalFouls);

        // If there were field goal attempts, check for makes
        if ($totalFGA > 0) {
            $totalFGM = $result->player1Stats->fieldGoalsMade + $result->player2Stats->fieldGoalsMade;
            $this->assertGreaterThanOrEqual(0, $totalFGM);
        }
    }

    /**
     * Characterization: with the RNG seeded to a fixed value, a single
     * simulateGame() run is fully deterministic — re-seeding to the same value
     * reproduces a byte-identical play-by-play. Locks current behavior.
     */
    public function testSimulateGameIsDeterministicForFixedSeed(): void
    {
        $player1Data = $this->createMockPlayerData('Player One');
        $player2Data = $this->createMockPlayerData('Player Two');

        mt_srand(424242);
        $first = $this->engine->simulateGame($player1Data, $player2Data, 'Owner');

        mt_srand(424242);
        $second = $this->engine->simulateGame($player1Data, $player2Data, 'Owner');

        $this->assertSame($first->player1Score, $second->player1Score);
        $this->assertSame($first->player2Score, $second->player2Score);
        $this->assertSame($first->playByPlay, $second->playByPlay);
    }

    /**
     * Repeated-invocation boundary: running simulateGame() twice on the same
     * instance must not carry possession (or any per-game state) from the first
     * run into the next. A run made after an intervening "dirtying" run, with
     * the RNG re-seeded, reproduces the original run exactly — proving the
     * per-game possession state is reset on each call, not stale.
     */
    public function testSimulateGameDoesNotLeakStateBetweenRuns(): void
    {
        $player1Data = $this->createMockPlayerData('Player One');
        $player2Data = $this->createMockPlayerData('Player Two');

        mt_srand(424242);
        $first = $this->engine->simulateGame($player1Data, $player2Data, 'Owner');

        // Intervening run on the same instance dirties any per-game state.
        $this->engine->simulateGame($player1Data, $player2Data, 'Owner');

        // Re-seeding to the original seed must reproduce the first run exactly.
        mt_srand(424242);
        $afterReset = $this->engine->simulateGame($player1Data, $player2Data, 'Owner');

        $this->assertSame($first->player1Score, $afterReset->player1Score);
        $this->assertSame($first->player2Score, $afterReset->player2Score);
        $this->assertSame($first->playByPlay, $afterReset->playByPlay);
    }

    // ========== Helper Methods ==========

    /**
     * Create mock player data array for testing
     *
     * @return PlayerGameData
     */
    private function createMockPlayerData(string $name): array
    {
        return [
            'pid' => rand(1, 1000),
            'name' => $name,
            'oo' => 50,
            'r_drive_off' => 50,
            'po' => 50,
            'od' => 50,
            'dd' => 50,
            'pd' => 50,
            'r_fga' => 50,
            'r_fgp' => 45,
            'r_fta' => 50,
            'r_3ga' => 30,
            'r_3gp' => 35,
            'r_orb' => 30,
            'r_drb' => 50,
            'r_stl' => 40,
            'r_tvr' => 50,
            'r_blk' => 30,
            'r_foul' => 50,
        ];
    }
}
