<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies that migration 172 (canonical_games CTE) collapses duplicate
 * boxscore rows for the same matchup into exactly one game, while still
 * counting genuinely distinct matchups on the same date as separate games.
 */
#[Group('database')]
class TeamWinLossViewDedupTest extends DatabaseTestCase
{
    /**
     * The real 2004 corruption shape: the same matchup appears twice in
     * ibl_box_scores_teams under two different game_of_that_day ordinals.
     *
     * Before migration 172 the four-column dedup key treated each ordinal as a
     * separate game, making teams appear to have played 83 games in an 82-game
     * season.  After migration 172 the canonical_games CTE groups by the matchup
     * triple (date, visitor_teamid, home_teamid) and keeps only the row with the
     * lowest game_of_that_day, so wins + losses must equal 1 for both teams.
     */
    public function testTeamWinLossViewCollapsesDuplicateBoxscoreIntoOneGame(): void
    {
        // January 2099 → GENERATED game_type = 1 (regular season); year = 2099.
        // visitor = Stars (teamid 2), home = Metros (teamid 1).
        // Production shape: two rows per game, one per team name.

        // First recording at game_of_that_day = 1.
        $this->insertTeamBoxscoreRow('2099-01-15', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2099-01-15', 'Stars',  1, 2, 1);

        // Duplicate recording at game_of_that_day = 5 — same matchup, different ordinal.
        $this->insertTeamBoxscoreRow('2099-01-15', 'Metros', 5, 2, 1);
        $this->insertTeamBoxscoreRow('2099-01-15', 'Stars',  5, 2, 1);

        $stmt = $this->db->prepare(
            'SELECT wins, losses FROM ibl_team_win_loss WHERE year = ? AND currentname = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare ibl_team_win_loss query: ' . $this->db->error);

        $year = 2099;

        // Metros is the home team — it wins (home_total 104 > visitor_total 85).
        $name = 'Metros';
        $stmt->bind_param('is', $year, $name);
        $stmt->execute();
        $metros = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($metros, 'No ibl_team_win_loss row found for Metros in 2099');
        self::assertSame(
            1,
            $metros['wins'] + $metros['losses'],
            sprintf(
                'Metros should have exactly 1 game (wins=%d, losses=%d); duplicate ordinal was not collapsed.',
                $metros['wins'],
                $metros['losses'],
            ),
        );

        // Stars is the visitor — it loses.
        $name = 'Stars';
        $stmt->bind_param('is', $year, $name);
        $stmt->execute();
        $stars = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($stars, 'No ibl_team_win_loss row found for Stars in 2099');
        self::assertSame(
            1,
            $stars['wins'] + $stars['losses'],
            sprintf(
                'Stars should have exactly 1 game (wins=%d, losses=%d); duplicate ordinal was not collapsed.',
                $stars['wins'],
                $stars['losses'],
            ),
        );

        $stmt->close();
    }

    /**
     * Negative path: two genuinely distinct matchups that share a team on the
     * same date must remain two separate games.
     *
     * An over-aggressive fix that collapses solely on (date, home_teamid) would
     * incorrectly merge these.  The canonical_games CTE must key on the full
     * triple (date, visitor_teamid, home_teamid), not just a partial key.
     */
    public function testTeamWinLossViewCountsTwoDistinctMatchupsOnSameDateSeparately(): void
    {
        // Matchup A: Stars (2) @ Metros (1) at game_of_that_day = 1.
        $this->insertTeamBoxscoreRow('2099-01-16', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2099-01-16', 'Stars',  1, 2, 1);

        // Matchup B: Cougars (3) @ Metros (1) at game_of_that_day = 2 — distinct matchup.
        $this->insertTeamBoxscoreRow('2099-01-16', 'Metros',  2, 3, 1);
        $this->insertTeamBoxscoreRow('2099-01-16', 'Cougars', 2, 3, 1);

        $stmt = $this->db->prepare(
            'SELECT wins, losses FROM ibl_team_win_loss WHERE year = ? AND currentname = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare ibl_team_win_loss query: ' . $this->db->error);

        // Metros hosts both matchups and wins both (home_total 104 > visitor_total 85).
        $year = 2099;
        $name = 'Metros';
        $stmt->bind_param('is', $year, $name);
        $stmt->execute();
        $metros = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($metros, 'No ibl_team_win_loss row found for Metros in 2099');
        self::assertSame(
            2,
            $metros['wins'] + $metros['losses'],
            sprintf(
                'Metros should have 2 games (wins=%d, losses=%d), one per distinct matchup; the view over-collapsed.',
                $metros['wins'],
                $metros['losses'],
            ),
        );

        $stmt->close();
    }
}
