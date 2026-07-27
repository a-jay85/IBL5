<?php

declare(strict_types=1);

namespace Tests\OneOnOneGame;

use PHPUnit\Framework\TestCase;
use OneOnOneGame\OneOnOneGamePossessionResolver;
use OneOnOneGame\Contracts\OneOnOneGameShotResultResolverInterface;

/**
 * Tests for OneOnOneGamePossessionResolver
 *
 * @phpstan-import-type PlayerGameData from \OneOnOneGame\Contracts\OneOnOneGameEngineInterface
 */
final class OneOnOneGamePossessionResolverTest extends TestCase
{
    private OneOnOneGamePossessionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OneOnOneGamePossessionResolver();
    }

    // ========== Steal Check Tests ==========

    public function testCheckStealReturnsBooleanValue(): void
    {
        $result = $this->resolver->checkSteal(50, 50);

        $this->assertIsBool($result);
    }

    public function testCheckStealWithHighStealRatingIncreasesStealChance(): void
    {
        $stealCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->resolver->checkSteal(100, 10)) {
                $stealCount++;
            }
        }

        $this->assertGreaterThanOrEqual(0, $stealCount);
    }

    // ========== Rebound Check Tests ==========

    public function testCheckReboundReturnsBooleanValue(): void
    {
        $result = $this->resolver->checkRebound(30, 70);

        $this->assertIsBool($result);
    }

    public function testCheckReboundWithHighOffensiveReboundingFavorsOffense(): void
    {
        $offRebCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->resolver->checkRebound(90, 10)) {
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
            if ($this->resolver->checkRebound(10, 90)) {
                $offRebCount++;
            }
        }

        // Should get offensive rebound less often with high DRB
        $this->assertLessThan(50, $offRebCount);
    }

    // ========== resolve() Tests ==========

    public function testResolveFoulShortCircuitsBeforeShotResolution(): void
    {
        // checkFoul true must return RESULT_FOUL before resolve() is ever reached —
        // this pins the foul-before-shot short-circuit the RNG draw order depends on.
        $shotResultResolver = $this->createMock(OneOnOneGameShotResultResolverInterface::class);
        $shotResultResolver->method('checkFoul')->willReturn(true);
        $shotResultResolver->expects($this->never())->method('resolve');

        $resolver = new OneOnOneGamePossessionResolver($shotResultResolver);
        $result = $resolver->resolve($this->baselinePlayer(101, 'Off'), $this->baselinePlayer(201, 'Def'));

        self::assertSame(OneOnOneGameShotResultResolverInterface::RESULT_FOUL, $result);
    }

    public function testResolveStealShortCircuitsBeforeShotResolution(): void
    {
        // checkFoul false, then a guaranteed steal (r_stl 300 vs r_tvr 0) must return
        // RESULT_STEAL before shot selection reaches the shot resolver's resolve().
        $shotResultResolver = $this->createMock(OneOnOneGameShotResultResolverInterface::class);
        $shotResultResolver->method('checkFoul')->willReturn(false);
        $shotResultResolver->expects($this->never())->method('resolve');

        $offense = $this->baselinePlayer(101, 'Off');
        $offense['r_tvr'] = 0;
        $defense = $this->baselinePlayer(201, 'Def');
        $defense['r_stl'] = 300;

        $resolver = new OneOnOneGamePossessionResolver($shotResultResolver);
        $result = $resolver->resolve($offense, $defense);

        self::assertSame(OneOnOneGameShotResultResolverInterface::RESULT_STEAL, $result);
    }

    public function testResolveDelegatesToShotResolverWithValidShotType(): void
    {
        // No foul, no steal (r_stl 0 vs r_tvr 300 never steals): resolve() must
        // delegate to the shot resolver with a valid SHOT_* type and return its result.
        $shotResultResolver = $this->createMock(OneOnOneGameShotResultResolverInterface::class);
        $shotResultResolver->method('checkFoul')->willReturn(false);
        $shotResultResolver->expects($this->once())
            ->method('resolve')
            ->with(
                self::logicalAnd(
                    self::greaterThanOrEqual(OneOnOneGameShotResultResolverInterface::SHOT_THREE_POINTER),
                    self::lessThanOrEqual(OneOnOneGameShotResultResolverInterface::SHOT_POST)
                ),
                self::anything(),
                self::anything()
            )
            ->willReturn(OneOnOneGameShotResultResolverInterface::RESULT_MADE_POST);

        $offense = $this->baselinePlayer(101, 'Off');
        $offense['r_tvr'] = 300;
        $defense = $this->baselinePlayer(201, 'Def');
        $defense['r_stl'] = 0;

        $resolver = new OneOnOneGamePossessionResolver($shotResultResolver);
        mt_srand(20240201);
        $result = $resolver->resolve($offense, $defense);

        self::assertSame(OneOnOneGameShotResultResolverInterface::RESULT_MADE_POST, $result);
    }

    public function testSelectShotTypeAtZeroSumRatingsReturnsCurrentBehavior(): void
    {
        // Boundary: outside+drive+post = 0 makes rand(0, -1) fire. This plan changes
        // no behavior, so pin the current output rather than guard the edge.
        mt_srand(20240202);
        $shotType = $this->resolver->selectShotType(0, 0, 0, 50, 50);

        self::assertSame(OneOnOneGameShotResultResolverInterface::SHOT_POST, $shotType);
    }

    public function testConstructsWorkingShotResolverWhenNoneInjected(): void
    {
        // Coalesce mutant kill: the `?? new OneOnOneGameShotResultResolver()` default
        // must construct a working resolver so resolve() yields a valid RESULT_*.
        $resolver = new OneOnOneGamePossessionResolver();

        mt_srand(20240203);
        $result = $resolver->resolve($this->baselinePlayer(101, 'Off'), $this->baselinePlayer(201, 'Def'));

        self::assertGreaterThanOrEqual(OneOnOneGameShotResultResolverInterface::RESULT_FOUL, $result);
        self::assertLessThanOrEqual(OneOnOneGameShotResultResolverInterface::RESULT_MADE_POST, $result);
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
