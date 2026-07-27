<?php

declare(strict_types=1);

namespace Tests\OneOnOneGame;

use PHPUnit\Framework\TestCase;
use OneOnOneGame\OneOnOneGameEngine;

/**
 * Golden-master characterization pins for OneOnOneGameEngine::simulateGame().
 *
 * CAPTURE PROCEDURE: every expected literal in this file was captured by running
 * this test once against the UNMODIFIED OneOnOneGameEngine and copying the actual
 * value out of the failure diff. Do not hand-edit them. If engine behavior is
 * changed intentionally, regenerate via the same procedure in a commit that says
 * so. A refactor that requires editing these literals is not a refactor.
 *
 * @covers \OneOnOneGame\OneOnOneGameEngine
 * @phpstan-import-type PlayerGameData from \OneOnOneGame\Contracts\OneOnOneGameEngineInterface
 */
class OneOnOneGameEngineCharacterizationTest extends TestCase
{
    private const PBP_SHOOTER = <<<'PBP'
The opening coin flip is tails, so Reggie Miller gets the ball to start.<br>Reggie Miller guns up a three-pointer that hangs on the lip of the rim before falling out.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 0, Reggie Miller 0</strong><p>Reggie Miller strips the ball away from Ray Allen.<br><strong>SCORE: Ray Allen 0, Reggie Miller 0</strong><p>Reggie Miller pulls up along the baseline and clanks it off the front of the iron.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 0, Reggie Miller 0</strong><p>Ray Allen shoots from beyond the arc and misses.<br>Reggie Miller gets the rebound.<br><strong>SCORE: Ray Allen 0, Reggie Miller 0</strong><p>Reggie Miller launches a three that hangs on the lip of the rim, but drops!<br><strong>SCORE: Ray Allen 0, Reggie Miller 3</strong><p>Ray Allen squeezes off a leaping leaner but it's no good.<br>Ray Allen gets the (offensive) rebound.<br><strong>SCORE: Ray Allen 0, Reggie Miller 3</strong><p>Ray Allen tosses up a trey and comes up dry.<br>Reggie Miller gets the rebound.<br><strong>SCORE: Ray Allen 0, Reggie Miller 3</strong><p>Reggie Miller from just inside the arc that caroms off the rim.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 0, Reggie Miller 3</strong><p>Ray Allen pulls up along the baseline that rattles around an in!<br><strong>SCORE: Ray Allen 2, Reggie Miller 3</strong><p>Reggie Miller takes a shot from outside the arc and the ball just won't stay down.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 2, Reggie Miller 3</strong><p>Reggie Miller fouls Ray Allen.<br>Ray Allen makes 2 of 2 free throws.<br><strong>SCORE: Ray Allen 4, Reggie Miller 3</strong><p>Reggie Miller elevates inside for a dunk but the shot is off-line.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 4, Reggie Miller 3</strong><p>Ray Allen guns up a three-pointer and misses.<br>Reggie Miller gets the rebound.<br><strong>SCORE: Ray Allen 4, Reggie Miller 3</strong><p>Reggie Miller fires from downtown and it drops through the bucket!<br><strong>SCORE: Ray Allen 4, Reggie Miller 6</strong><p>Ray Allen tosses up a trey that swishes cleanly through the net!<br><strong>SCORE: Ray Allen 7, Reggie Miller 6</strong><p>Reggie Miller squeezes off a leaping leaner and misses.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 7, Reggie Miller 6</strong><p>Ray Allen fakes left and drives right and it rattles around and out.<br>Reggie Miller gets the rebound.<br><strong>SCORE: Ray Allen 7, Reggie Miller 6</strong><p>Reggie Miller chucks a long-range bomb and the ball just won't stay down.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 7, Reggie Miller 6</strong><p>Reggie Miller fouls Ray Allen.<br>Ray Allen misses both free throws.<br><strong>SCORE: Ray Allen 7, Reggie Miller 6</strong><p>Ray Allen fires from downtown and practically wills it home!<br><strong>SCORE: Ray Allen 10, Reggie Miller 6</strong><p>Reggie Miller tosses up a trey but can't connect.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 10, Reggie Miller 6</strong><p>Ray Allen attempts a trifecta that drops through the hoop!<br><strong>SCORE: Ray Allen 13, Reggie Miller 6</strong><p>Reggie Miller fires from downtown but can't connect.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 13, Reggie Miller 6</strong><p>Ray Allen fires from downtown and practically wills it home!<br><strong>SCORE: Ray Allen 16, Reggie Miller 6</strong><p>Ray Allen steals the ball from Reggie Miller.<br><strong>SCORE: Ray Allen 16, Reggie Miller 6</strong><p>Ray Allen chucks a long-range bomb but the shot is off-line.<br>Reggie Miller gets the rebound.<br><strong>SCORE: Ray Allen 16, Reggie Miller 6</strong><p>Ray Allen fouls Reggie Miller.<br>Reggie Miller makes 2 of 2 free throws.<br><strong>SCORE: Ray Allen 16, Reggie Miller 8</strong><p>Ray Allen sets, fires,  that hits nothing but net!<br><strong>SCORE: Ray Allen 18, Reggie Miller 8</strong><p>Reggie Miller slashes into the lane and it rattles around and out.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 18, Reggie Miller 8</strong><p>Ray Allen backs down and takes a little turnaround jumper but can't get it to fall.<br>Reggie Miller gets the rebound.<br><strong>SCORE: Ray Allen 18, Reggie Miller 8</strong><p>Reggie Miller from just inside the arc but comes up empty.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 18, Reggie Miller 8</strong><p>Ray Allen fakes left and drives right and somehow the ball stays out.<br>Reggie Miller gets the rebound.<br><strong>SCORE: Ray Allen 18, Reggie Miller 8</strong><p>Reggie Miller fakes left and drives right but it's no good.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 18, Reggie Miller 8</strong><p>Ray Allen from just inside the arc and knocks it down!<br><strong>SCORE: Ray Allen 20, Reggie Miller 8</strong><p>Reggie Miller attempts a trifecta and bounces it off the glass and out.<br>Reggie Miller gets the (offensive) rebound.<br><strong>SCORE: Ray Allen 20, Reggie Miller 8</strong><p>Reggie Miller chucks a long-range bomb and clanks it off the front of the iron.<br>Ray Allen gets the rebound.<br><strong>SCORE: Ray Allen 20, Reggie Miller 8</strong><p>Ray Allen guns up a three-pointer that bounces off the front of the rim, off the back of the rim, then drops!<br><strong>SCORE: Ray Allen 23, Reggie Miller 8</strong><p><div class="table-scroll-wrapper"><div class="table-scroll-container"><table class="ibl-data-table"><thead><tr><th colspan="13"><span class="text-accent-500">FINAL SCORE: Ray Allen 23, Reggie Miller 8</span></th></tr><tr><th>Name</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>STL</th><th>BLK</th><th>TVR</th><th>FOUL</th></tr></thead><tbody><tr><td>Ray Allen</td><td>8</td><td>16</td><td>2</td><td>4</td><td>5</td><td>9</td><td>1</td><td>14</td><td>1</td><td>0</td><td>1</td><td>1</td></tr><tr><td>Reggie Miller</td><td>2</td><td>16</td><td>2</td><td>2</td><td>2</td><td>9</td><td>1</td><td>8</td><td>1</td><td>0</td><td>1</td><td>2</td></tr></tbody></table></div></div>
PBP;

    private const PBP_POST = <<<'PBP'
The opening coin flip is tails, so Ewing gets the ball to start.<br>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 0, Ewing 2</strong><p>Ewing strips the ball away from Shaq.<br><strong>SCORE: Shaq 0, Ewing 2</strong><p>Ewing elevates for a J but can't get it to fall.<br>Shaq gets the rebound.<br><strong>SCORE: Shaq 0, Ewing 2</strong><p>Ewing fouls Shaq.<br>Shaq makes 2 of 2 free throws.<br><strong>SCORE: Shaq 2, Ewing 2</strong><p>Ewing flips up a finger roll but the shot is off-line.<br>Shaq gets the rebound.<br><strong>SCORE: Shaq 2, Ewing 2</strong><p>Shaq fires a shot from near the top of the key but can't get it to fall.<br>Ewing gets the rebound.<br><strong>SCORE: Shaq 2, Ewing 2</strong><p>Ewing drives to the basket and it's an airball!<br>Shaq gets the rebound.<br><strong>SCORE: Shaq 2, Ewing 2</strong><p>Ewing gets a clean pick of Shaq.<br><strong>SCORE: Shaq 2, Ewing 2</strong><p>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 2, Ewing 4</strong><p>Shaq takes a sweeping skyhook as he powers into the lane but comes up empty.<br>Shaq gets the (offensive) rebound.<br><strong>SCORE: Shaq 2, Ewing 4</strong><p>Ewing fouls Shaq.<br>Shaq makes 1 of 2 free throws.<br><strong>SCORE: Shaq 3, Ewing 4</strong><p>Ewing fires a shot from near the top of the key but can't connect.<br>Shaq gets the rebound.<br><strong>SCORE: Shaq 3, Ewing 4</strong><p>Ewing fouls Shaq.<br>Shaq makes 1 of 2 free throws.<br><strong>SCORE: Shaq 4, Ewing 4</strong><p>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 4, Ewing 6</strong><p>Ewing fouls Shaq.<br>Shaq makes 2 of 2 free throws.<br><strong>SCORE: Shaq 6, Ewing 6</strong><p>Ewing from just inside the arc but Shaq slaps the ball away.<br>Shaq gets the rebound.<br><strong>SCORE: Shaq 6, Ewing 6</strong><p>Shaq slashes into the lane and clanks it off the front of the iron.<br>Shaq gets the (offensive) rebound.<br><strong>SCORE: Shaq 6, Ewing 6</strong><p>Ewing fouls Shaq.<br>Shaq makes 1 of 2 free throws.<br><strong>SCORE: Shaq 7, Ewing 6</strong><p>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 7, Ewing 8</strong><p>Shaq with a jump hook from the low block but Ewing comes up with the block.<br>Ewing gets the rebound.<br><strong>SCORE: Shaq 7, Ewing 8</strong><p>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 7, Ewing 10</strong><p>Shaq lifts up a teardrop on the drive but it's no good.<br>Ewing gets the rebound.<br><strong>SCORE: Shaq 7, Ewing 10</strong><p>Ewing sets, fires,  that caroms off the rim.<br>Ewing gets the (offensive) rebound.<br><strong>SCORE: Shaq 7, Ewing 10</strong><p>Ewing goes to the up-and-under move and it's an airball!<br>Shaq gets the rebound.<br><strong>SCORE: Shaq 7, Ewing 10</strong><p>Shaq backs down and takes a little turnaround jumper but Ewing slaps the ball away.<br>Ewing gets the rebound.<br><strong>SCORE: Shaq 7, Ewing 10</strong><p>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 7, Ewing 12</strong><p>Ewing fouls Shaq.<br>Shaq makes 2 of 2 free throws.<br><strong>SCORE: Shaq 9, Ewing 12</strong><p>Shaq fouls Ewing.<br>Ewing makes 1 of 2 free throws.<br><strong>SCORE: Shaq 9, Ewing 13</strong><p>Shaq lofts up a soft fadeaway from the paint and the shot comes up short.<br>Shaq gets the (offensive) rebound.<br><strong>SCORE: Shaq 9, Ewing 13</strong><p>Ewing fouls Shaq.<br>Shaq makes 2 of 2 free throws.<br><strong>SCORE: Shaq 11, Ewing 13</strong><p>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 11, Ewing 15</strong><p>Ewing fouls Shaq.<br>Shaq misses both free throws.<br><strong>SCORE: Shaq 11, Ewing 15</strong><p>Shaq goes to the up-and-under move and the shot is a bit long.<br>Shaq gets the (offensive) rebound.<br><strong>SCORE: Shaq 11, Ewing 15</strong><p>Ewing fouls Shaq.<br>Shaq makes 1 of 2 free throws.<br><strong>SCORE: Shaq 12, Ewing 15</strong><p>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 12, Ewing 17</strong><p>Ewing fouls Shaq.<br>Shaq makes 2 of 2 free throws.<br><strong>SCORE: Shaq 14, Ewing 17</strong><p>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 14, Ewing 19</strong><p>Shaq with a jump hook from the low block but Ewing tips the shot attempt away.<br>Shaq gets the (offensive) rebound.<br><strong>SCORE: Shaq 14, Ewing 19</strong><p>Shaq with a jump hook from the low block and the ball just won't stay down.<br>Ewing gets the rebound.<br><strong>SCORE: Shaq 14, Ewing 19</strong><p>Shaq fouls Ewing.<br>Ewing makes 1 of 2 free throws.<br><strong>SCORE: Shaq 14, Ewing 20</strong><p>Shaq flips up a finger roll that caroms off the rim.<br>Ewing gets the rebound.<br><strong>SCORE: Shaq 14, Ewing 20</strong><p>Ewing slashes into the lane and the shot comes up short.<br>Shaq gets the rebound.<br><strong>SCORE: Shaq 14, Ewing 20</strong><p>Shaq gets free for a drive with a nifty crossover but Ewing tips the shot attempt away.<br>Ewing gets the rebound.<br><strong>SCORE: Shaq 14, Ewing 20</strong><p>Ewing spins into the paint on a drive but can't connect.<br>Ewing gets the (offensive) rebound.<br><strong>SCORE: Shaq 14, Ewing 20</strong><p>Shaq fouls Ewing.<br>Ewing makes 2 of 2 free throws.<br><strong>SCORE: Shaq 14, Ewing 22</strong><p><div class="table-scroll-wrapper"><div class="table-scroll-container"><table class="ibl-data-table"><thead><tr><th colspan="13"><span class="text-accent-500">FINAL SCORE: Shaq 14, Ewing 22</span></th></tr><tr><th>Name</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>STL</th><th>BLK</th><th>TVR</th><th>FOUL</th></tr></thead><tbody><tr><td>Shaq</td><td>0</td><td>12</td><td>14</td><td>20</td><td>0</td><td>0</td><td>5</td><td>12</td><td>0</td><td>1</td><td>2</td><td>12</td></tr><tr><td>Ewing</td><td>0</td><td>9</td><td>22</td><td>24</td><td>0</td><td>0</td><td>2</td><td>9</td><td>2</td><td>4</td><td>0</td><td>10</td></tr></tbody></table></div></div>
PBP;

    /** @return PlayerGameData */
    private function shooterOne(): array
    {
        return [
            'pid' => 101, 'name' => 'Ray Allen',
            'oo' => 85, 'r_drive_off' => 35, 'po' => 20,
            'od' => 50, 'dd' => 50, 'pd' => 50,
            'r_fga' => 50, 'r_fgp' => 58, 'r_fta' => 30,
            'r_3ga' => 75, 'r_3gp' => 62, 'r_orb' => 15, 'r_drb' => 50,
            'r_stl' => 40, 'r_tvr' => 50, 'r_blk' => 30, 'r_foul' => 50,
        ];
    }

    /** @return PlayerGameData */
    private function shooterTwo(): array
    {
        return [
            'pid' => 102, 'name' => 'Reggie Miller',
            'oo' => 80, 'r_drive_off' => 30, 'po' => 25,
            'od' => 45, 'dd' => 55, 'pd' => 40,
            'r_fga' => 50, 'r_fgp' => 55, 'r_fta' => 32,
            'r_3ga' => 70, 'r_3gp' => 58, 'r_orb' => 18, 'r_drb' => 50,
            'r_stl' => 35, 'r_tvr' => 50, 'r_blk' => 15, 'r_foul' => 50,
        ];
    }

    /** @return PlayerGameData */
    private function postOne(): array
    {
        return [
            'pid' => 201, 'name' => 'Shaq',
            'oo' => 20, 'r_drive_off' => 60, 'po' => 90,
            'od' => 50, 'dd' => 65, 'pd' => 80,
            'r_fga' => 50, 'r_fgp' => 62, 'r_fta' => 70,
            'r_3ga' => 2, 'r_3gp' => 10, 'r_orb' => 70, 'r_drb' => 50,
            'r_stl' => 20, 'r_tvr' => 50, 'r_blk' => 75, 'r_foul' => 50,
        ];
    }

    /** @return PlayerGameData */
    private function postTwo(): array
    {
        return [
            'pid' => 202, 'name' => 'Ewing',
            'oo' => 25, 'r_drive_off' => 50, 'po' => 85,
            'od' => 50, 'dd' => 60, 'pd' => 78,
            'r_fga' => 50, 'r_fgp' => 55, 'r_fta' => 65,
            'r_3ga' => 3, 'r_3gp' => 12, 'r_orb' => 65, 'r_drb' => 50,
            'r_stl' => 25, 'r_tvr' => 50, 'r_blk' => 70, 'r_foul' => 50,
        ];
    }

    public function testShooterHeavyMatchupIsPinnedForSeed424242(): void
    {
        mt_srand(424242);
        $engine = new OneOnOneGameEngine();
        $result = $engine->simulateGame($this->shooterOne(), $this->shooterTwo(), 'PinOwner');

        self::assertSame('The opening coin flip is tails, so Reggie Miller gets the ball to start.<br>', $result->coinFlipResult);
        self::assertSame('Ray Allen', $result->player1Name);
        self::assertSame('Reggie Miller', $result->player2Name);
        self::assertSame(23, $result->player1Score);
        self::assertSame(8, $result->player2Score);

        self::assertSame([
            'fga' => 16, 'fgm' => 8, 'fta' => 4, 'ftm' => 2,
            '3pa' => 9, '3pm' => 5, 'orb' => 1, 'trb' => 14,
            'stl' => 1, 'blk' => 0, 'tvr' => 1, 'foul' => 1,
        ], [
            'fga' => $result->player1Stats->fieldGoalsAttempted,
            'fgm' => $result->player1Stats->fieldGoalsMade,
            'fta' => $result->player1Stats->freeThrowsAttempted,
            'ftm' => $result->player1Stats->freeThrowsMade,
            '3pa' => $result->player1Stats->threePointersAttempted,
            '3pm' => $result->player1Stats->threePointersMade,
            'orb' => $result->player1Stats->offensiveRebounds,
            'trb' => $result->player1Stats->totalRebounds,
            'stl' => $result->player1Stats->steals,
            'blk' => $result->player1Stats->blocks,
            'tvr' => $result->player1Stats->turnovers,
            'foul' => $result->player1Stats->fouls,
        ], 'player1 stat line');

        self::assertSame([
            'fga' => 16, 'fgm' => 2, 'fta' => 2, 'ftm' => 2,
            '3pa' => 9, '3pm' => 2, 'orb' => 1, 'trb' => 8,
            'stl' => 1, 'blk' => 0, 'tvr' => 1, 'foul' => 2,
        ], [
            'fga' => $result->player2Stats->fieldGoalsAttempted,
            'fgm' => $result->player2Stats->fieldGoalsMade,
            'fta' => $result->player2Stats->freeThrowsAttempted,
            'ftm' => $result->player2Stats->freeThrowsMade,
            '3pa' => $result->player2Stats->threePointersAttempted,
            '3pm' => $result->player2Stats->threePointersMade,
            'orb' => $result->player2Stats->offensiveRebounds,
            'trb' => $result->player2Stats->totalRebounds,
            'stl' => $result->player2Stats->steals,
            'blk' => $result->player2Stats->blocks,
            'tvr' => $result->player2Stats->turnovers,
            'foul' => $result->player2Stats->fouls,
        ], 'player2 stat line');

        $expectedPbp = self::PBP_SHOOTER . "\n";
        self::assertSame(explode('<br>', $expectedPbp), explode('<br>', $result->playByPlay),
            'Play-by-play diverged — the array diff names the segment.');
        self::assertSame($expectedPbp, $result->playByPlay);
    }

    public function testPostHeavyMatchupIsPinnedForSeed1337(): void
    {
        mt_srand(1337);
        $engine = new OneOnOneGameEngine();
        $result = $engine->simulateGame($this->postOne(), $this->postTwo(), 'PinOwner');

        self::assertSame('The opening coin flip is tails, so Ewing gets the ball to start.<br>', $result->coinFlipResult);
        self::assertSame('Shaq', $result->player1Name);
        self::assertSame('Ewing', $result->player2Name);
        self::assertSame(14, $result->player1Score);
        self::assertSame(22, $result->player2Score);

        self::assertSame([
            'fga' => 12, 'fgm' => 0, 'fta' => 20, 'ftm' => 14,
            '3pa' => 0, '3pm' => 0, 'orb' => 5, 'trb' => 12,
            'stl' => 0, 'blk' => 1, 'tvr' => 2, 'foul' => 12,
        ], [
            'fga' => $result->player1Stats->fieldGoalsAttempted,
            'fgm' => $result->player1Stats->fieldGoalsMade,
            'fta' => $result->player1Stats->freeThrowsAttempted,
            'ftm' => $result->player1Stats->freeThrowsMade,
            '3pa' => $result->player1Stats->threePointersAttempted,
            '3pm' => $result->player1Stats->threePointersMade,
            'orb' => $result->player1Stats->offensiveRebounds,
            'trb' => $result->player1Stats->totalRebounds,
            'stl' => $result->player1Stats->steals,
            'blk' => $result->player1Stats->blocks,
            'tvr' => $result->player1Stats->turnovers,
            'foul' => $result->player1Stats->fouls,
        ], 'player1 stat line');

        self::assertSame([
            'fga' => 9, 'fgm' => 0, 'fta' => 24, 'ftm' => 22,
            '3pa' => 0, '3pm' => 0, 'orb' => 2, 'trb' => 9,
            'stl' => 2, 'blk' => 4, 'tvr' => 0, 'foul' => 10,
        ], [
            'fga' => $result->player2Stats->fieldGoalsAttempted,
            'fgm' => $result->player2Stats->fieldGoalsMade,
            'fta' => $result->player2Stats->freeThrowsAttempted,
            'ftm' => $result->player2Stats->freeThrowsMade,
            '3pa' => $result->player2Stats->threePointersAttempted,
            '3pm' => $result->player2Stats->threePointersMade,
            'orb' => $result->player2Stats->offensiveRebounds,
            'trb' => $result->player2Stats->totalRebounds,
            'stl' => $result->player2Stats->steals,
            'blk' => $result->player2Stats->blocks,
            'tvr' => $result->player2Stats->turnovers,
            'foul' => $result->player2Stats->fouls,
        ], 'player2 stat line');

        $expectedPbp = self::PBP_POST . "\n";
        self::assertSame(explode('<br>', $expectedPbp), explode('<br>', $result->playByPlay),
            'Play-by-play diverged — the array diff names the segment.');
        self::assertSame($expectedPbp, $result->playByPlay);
    }
}
