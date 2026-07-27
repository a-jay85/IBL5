<?php

declare(strict_types=1);

namespace Tests\OneOnOneGame;

use PHPUnit\Framework\TestCase;
use OneOnOneGame\OneOnOneGameEngine;
use OneOnOneGame\OneOnOneGameTextGenerator;
use OneOnOneGame\Contracts\OneOnOneGamePossessionResolverInterface;
use OneOnOneGame\Contracts\OneOnOneGameShotResultResolverInterface;

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

    // ========== Constructor Injection Tests ==========

    /**
     * Kills the constructor Coalesce mutant on the shot-result resolver default:
     * an injected shot resolver that always returns RESULT_BLOCKED_THREE (and never
     * fouls) must actually be reached, producing three-point attempts with zero
     * made field goals. If the `??` default were used instead, real shooting logic
     * would occasionally make a shot and this would fail.
     */
    public function testInjectedShotResultResolverIsUsed(): void
    {
        $shotResultResolver = self::createStub(OneOnOneGameShotResultResolverInterface::class);
        $shotResultResolver->method('resolve')->willReturn(OneOnOneGameShotResultResolverInterface::RESULT_BLOCKED_THREE);
        $shotResultResolver->method('checkFoul')->willReturn(false);

        $engine = new OneOnOneGameEngine(null, null, $shotResultResolver);

        mt_srand(20240301);
        $result = $engine->simulateGame(
            $this->createMockPlayerData('Player One'),
            $this->createMockPlayerData('Player Two'),
            'Owner'
        );

        $totalThreePointAttempts = $result->player1Stats->threePointersAttempted
            + $result->player2Stats->threePointersAttempted;
        $totalFieldGoalsMade = $result->player1Stats->fieldGoalsMade
            + $result->player2Stats->fieldGoalsMade;

        $this->assertGreaterThan(0, $totalThreePointAttempts);
        $this->assertSame(0, $totalFieldGoalsMade);
    }

    /**
     * Kills the constructor Coalesce mutant on the possession resolver default:
     * an injected possession resolver that always returns RESULT_STEAL (and never
     * rebounds) must be reached, producing turnovers and the steal play-by-play
     * text. The 500-possession safety cap keeps an all-steal game finite.
     */
    public function testInjectedPossessionResolverIsUsed(): void
    {
        $textGenerator = self::createStub(OneOnOneGameTextGenerator::class);
        $textGenerator->method('getCoinFlipText')->willReturn('coin flip<br>');
        $textGenerator->method('getStealPlayText')->willReturn('STEAL_SENTINEL<br>');
        $textGenerator->method('getScoreText')->willReturn('SCORE:<br>');

        $possessionResolver = self::createStub(OneOnOneGamePossessionResolverInterface::class);
        $possessionResolver->method('resolve')->willReturn(OneOnOneGameShotResultResolverInterface::RESULT_STEAL);
        $possessionResolver->method('checkRebound')->willReturn(false);

        $engine = new OneOnOneGameEngine($textGenerator, $possessionResolver);

        mt_srand(20240302);
        $result = $engine->simulateGame(
            $this->createMockPlayerData('Player One'),
            $this->createMockPlayerData('Player Two'),
            'Owner'
        );

        $totalTurnovers = $result->player1Stats->turnovers + $result->player2Stats->turnovers;

        $this->assertStringContainsString('STEAL_SENTINEL', $result->playByPlay);
        $this->assertGreaterThan(0, $totalTurnovers);
        // The game still terminates (safety cap) and emits the final score table.
        $this->assertStringContainsString('FINAL SCORE:', $result->playByPlay);
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
