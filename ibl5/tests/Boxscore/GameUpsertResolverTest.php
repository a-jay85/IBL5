<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use Boxscore\BoxscoreRepository;
use Boxscore\GameUpsertResolver;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \Boxscore\GameUpsertResolver
 */
class GameUpsertResolverTest extends TestCase
{
    /**
     * Build a minimal 58-char game info line for testing.
     *
     * Format: 2-char month offset, 2-char day offset, 2-char game#,
     * 2-char visitor, 2-char home, 5-char attendance, 5-char capacity,
     * then W/L and quarter scores to fill 58 chars total.
     */
    private function buildGameInfoLine(int $monthOffset = 0, int $dayOffset = 14): string
    {
        // Month offset (0=Oct), day offset (0=day 1), game#=0, visitor=0, home=1
        $line = sprintf('%02d', $monthOffset)  // month offset from Oct
              . sprintf('%02d', $dayOffset)     // day offset (0-indexed)
              . '00'                            // game of that day
              . '00'                            // visitor team (0-indexed → teamid 1)
              . '01'                            // home team (0-indexed → teamid 2)
              . '18000'                         // attendance
              . '20000'                         // capacity
              . '1005'                          // visitor wins/losses
              . '0510'                          // home wins/losses
              . '025030028027000'               // visitor quarter scores (5x3 chars)
              . '022031025030000';              // home quarter scores (5x3 chars)

        return $line;
    }

    public function testResolveReturnsInsertForNewGame(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns null (no matching game)
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', []);

        $repository = new BoxscoreRepository($mockDb);
        $resolver = new GameUpsertResolver($repository);

        $boxscore = Boxscore::withGameInfoLine($this->buildGameInfoLine(3, 10), 2026, 'Regular Season/Playoffs');

        $this->assertSame('insert', $resolver->resolve($boxscore));
    }

    public function testResolveReturnsSkipWhenScoresMatchAndNoNullTeamId(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns matching quarter scores
        // buildGameInfoLine defaults: visitor Q scores = 025,030,028,027,000 = 110; home = 022,031,025,030,000 = 108
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30, 'visitor_q3_points' => 28,
            'visitor_q4_points' => 27, 'visitor_ot_points' => 0,
            'home_q1_points' => 22, 'home_q2_points' => 31, 'home_q3_points' => 25,
            'home_q4_points' => 30, 'home_ot_points' => 0,
        ]]);
        // hasNullTeamIdPlayerBoxscores returns false (cnt=0)
        $mockDb->onQuery('(?s)COUNT.*teamid IS NULL', [['cnt' => 0]]);

        $repository = new BoxscoreRepository($mockDb);
        $resolver = new GameUpsertResolver($repository);

        $boxscore = Boxscore::withGameInfoLine($this->buildGameInfoLine(3, 10), 2026, 'Regular Season/Playoffs');

        $this->assertSame('skip', $resolver->resolve($boxscore));
    }

    public function testResolveReturnsUpdateWhenScoresDiffer(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns different scores (all zeros — clearly different)
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitor_q1_points' => 0, 'visitor_q2_points' => 0, 'visitor_q3_points' => 0,
            'visitor_q4_points' => 0, 'visitor_ot_points' => 0,
            'home_q1_points' => 0, 'home_q2_points' => 0, 'home_q3_points' => 0,
            'home_q4_points' => 0, 'home_ot_points' => 0,
        ]]);

        $repository = new BoxscoreRepository($mockDb);
        $resolver = new GameUpsertResolver($repository);

        $boxscore = Boxscore::withGameInfoLine($this->buildGameInfoLine(3, 10), 2026, 'Regular Season/Playoffs');

        $this->assertSame('update', $resolver->resolve($boxscore));
    }

    public function testResolveReturnsUpdateWhenScoresMatchButNullTeamId(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(true);
        // findTeamBoxscore returns matching scores
        $mockDb->onQuery('(?s)SELECT.*ibl_box_scores_teams.*WHERE', [[
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30, 'visitor_q3_points' => 28,
            'visitor_q4_points' => 27, 'visitor_ot_points' => 0,
            'home_q1_points' => 22, 'home_q2_points' => 31, 'home_q3_points' => 25,
            'home_q4_points' => 30, 'home_ot_points' => 0,
        ]]);
        // hasNullTeamIdPlayerBoxscores returns true (cnt=1)
        $mockDb->onQuery('(?s)COUNT.*teamid IS NULL', [['cnt' => 1]]);

        $repository = new BoxscoreRepository($mockDb);
        $resolver = new GameUpsertResolver($repository);

        $boxscore = Boxscore::withGameInfoLine($this->buildGameInfoLine(3, 10), 2026, 'Regular Season/Playoffs');

        $this->assertSame('update', $resolver->resolve($boxscore));
    }
}
