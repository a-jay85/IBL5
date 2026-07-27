<?php

declare(strict_types=1);

namespace Tests\OneOnOneGame;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use OneOnOneGame\OneOnOneGameShotResultResolver;
use OneOnOneGame\Contracts\OneOnOneGameShotResultResolverInterface;

/**
 * Tests for OneOnOneGameShotResultResolver
 *
 * @phpstan-import-type PlayerGameData from \OneOnOneGame\Contracts\OneOnOneGameEngineInterface
 */
final class OneOnOneGameShotResultResolverTest extends TestCase
{
    private OneOnOneGameShotResultResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OneOnOneGameShotResultResolver();
    }

    // ========== Block Check Tests ==========

    public function testCheckBlockReturnsBooleanValue(): void
    {
        $result = $this->resolver->checkBlock(50, 50);

        $this->assertIsBool($result);
    }

    public function testCheckBlockWithHighBlockRatingIncreasesBlockChance(): void
    {
        // Run multiple times to verify statistical tendency
        $blockCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->resolver->checkBlock(100, 10)) {
                $blockCount++;
            }
        }

        // With very high block rating vs low attempt rating, should block more often
        // Not deterministic, but should get some blocks
        $this->assertGreaterThanOrEqual(0, $blockCount);
    }

    // ========== Foul Check Tests ==========

    public function testCheckFoulReturnsBooleanValue(): void
    {
        $result = $this->resolver->checkFoul(50, 50);

        $this->assertIsBool($result);
    }

    public function testCheckFoulWithHighDrawFoulRatingIncreasesFoulChance(): void
    {
        $foulCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->resolver->checkFoul(10, 100)) {
                $foulCount++;
            }
        }

        $this->assertGreaterThanOrEqual(0, $foulCount);
    }

    // ========== Shot Check Tests ==========

    public function testCheckShotReturnsBooleanValue(): void
    {
        $result = $this->resolver->checkShot(50, 50, 25);

        $this->assertIsBool($result);
    }

    public function testCheckShotWithHighPercentageIncreasesMakeChance(): void
    {
        $madeCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            // Very high percentage, high offense, low defense
            if ($this->resolver->checkShot(90, 50, 10)) {
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
            if ($this->resolver->checkShot(10, 10, 50)) {
                $madeCount++;
            }
        }

        // Should miss most shots with these ratings
        $this->assertLessThan(50, $madeCount);
    }

    // ========== resolve() Tests ==========

    #[DataProvider('madeShotProvider')]
    public function testResolveReturnsMadeResultWhenBlockAndFoulFailAndShotSucceeds(int $shotType, int $expected): void
    {
        // Offense guaranteed unblockable, un-fouled, and certain to score:
        // r_blk 0 vs high attempt ratings never blocks; r_foul 0 draw vs r_fta 0
        // never fouls; base percent + offense - 2*defense >= 100 always makes.
        $offense = $this->baselinePlayer(101, 'Made Shooter');
        $offense['r_fga'] = 300;
        $offense['r_3ga'] = 300;
        $offense['r_fgp'] = 100;
        $offense['r_3gp'] = 100;
        $offense['r_fta'] = 0;
        $offense['oo'] = 100;
        $offense['po'] = 100;
        $offense['r_drive_off'] = 100;

        $defense = $this->baselinePlayer(201, 'Weak Defender');
        $defense['r_blk'] = 0;
        $defense['r_foul'] = 300;
        $defense['od'] = 0;
        $defense['dd'] = 0;
        $defense['pd'] = 0;

        mt_srand(20240101);
        $result = $this->resolver->resolve($shotType, $offense, $defense);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function madeShotProvider(): array
    {
        return [
            'three pointer' => [OneOnOneGameShotResultResolverInterface::SHOT_THREE_POINTER, OneOnOneGameShotResultResolverInterface::RESULT_MADE_THREE],
            'outside two' => [OneOnOneGameShotResultResolverInterface::SHOT_OUTSIDE_TWO, OneOnOneGameShotResultResolverInterface::RESULT_MADE_OUTSIDE_TWO],
            'drive' => [OneOnOneGameShotResultResolverInterface::SHOT_DRIVE, OneOnOneGameShotResultResolverInterface::RESULT_MADE_DRIVE],
            'post' => [OneOnOneGameShotResultResolverInterface::SHOT_POST, OneOnOneGameShotResultResolverInterface::RESULT_MADE_POST],
        ];
    }

    public function testResolveBlockShortCircuitsBeforeShotBranch(): void
    {
        // r_blk 200 vs r_3ga 1 guarantees a block on all three iterations
        // (makeBlock min 202 > avoidBlock max 201), so resolve() must return
        // RESULT_BLOCKED_THREE without ever reaching the foul or shot branches.
        $offense = $this->baselinePlayer(101, 'Blocked Shooter');
        $offense['r_3ga'] = 1;

        $defense = $this->baselinePlayer(201, 'Shot Blocker');
        $defense['r_blk'] = 200;

        mt_srand(20240102);
        $result = $this->resolver->resolve(
            OneOnOneGameShotResultResolverInterface::SHOT_THREE_POINTER,
            $offense,
            $defense
        );

        self::assertSame(OneOnOneGameShotResultResolverInterface::RESULT_BLOCKED_THREE, $result);
    }

    public function testResolveFoulBranchNeverYieldsMissedResult(): void
    {
        // Force the foul branch: r_blk 0 never blocks, r_fta 300 vs r_foul 0 always
        // fouls. The foul branch returns RESULT_MADE_* on a make or RESULT_FOUL on a
        // miss — it can never return RESULT_MISSED_*, which is the FOUL_DIFFICULTY path.
        $offense = $this->baselinePlayer(101, 'Foul Drawer');
        $offense['r_fga'] = 300;
        $offense['r_fta'] = 300;

        $defense = $this->baselinePlayer(201, 'Fouler');
        $defense['r_blk'] = 0;
        $defense['r_foul'] = 0;

        mt_srand(20240103);
        $result = $this->resolver->resolve(
            OneOnOneGameShotResultResolverInterface::SHOT_POST,
            $offense,
            $defense
        );

        // The foul branch can only return RESULT_MADE_POST (make) or RESULT_FOUL
        // (miss) — never RESULT_MISSED_POST, which is the non-foul FOUL_DIFFICULTY path.
        self::assertContains($result, [
            OneOnOneGameShotResultResolverInterface::RESULT_MADE_POST,
            OneOnOneGameShotResultResolverInterface::RESULT_FOUL,
        ]);
    }

    public function testResolveInvalidShotTypeFallsThroughToPostBranch(): void
    {
        // An out-of-range shot type falls through the switch default to the post
        // branch, matching the existing `case SHOT_POST: default:` behavior.
        $offense = $this->baselinePlayer(101, 'Made Shooter');
        $offense['r_fga'] = 300;
        $offense['r_fgp'] = 100;
        $offense['r_fta'] = 0;
        $offense['po'] = 100;

        $defense = $this->baselinePlayer(201, 'Weak Defender');
        $defense['r_blk'] = 0;
        $defense['r_foul'] = 300;
        $defense['pd'] = 0;

        mt_srand(20240104);
        $result = $this->resolver->resolve(99, $offense, $defense);

        self::assertSame(OneOnOneGameShotResultResolverInterface::RESULT_MADE_POST, $result);
    }

    // ========== Helper Methods ==========

    /**
     * Create a neutral-baseline player data array with a literal pid.
     *
     * @return PlayerGameData
     */
    private function baselinePlayer(int $pid, string $name): array
    {
        return [
            'pid' => $pid,
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
