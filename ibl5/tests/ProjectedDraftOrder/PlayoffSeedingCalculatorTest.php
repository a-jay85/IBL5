<?php

declare(strict_types=1);

namespace Tests\ProjectedDraftOrder;

use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;
use ProjectedDraftOrder\NonHeadToHeadTiebreaker;
use ProjectedDraftOrder\PlayoffSeedingCalculator;

/**
 * @covers \ProjectedDraftOrder\PlayoffSeedingCalculator
 * @phpstan-import-type StandingsRow from ProjectedDraftOrderRepositoryInterface
 */
class PlayoffSeedingCalculatorTest extends TestCase
{
    private PlayoffSeedingCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PlayoffSeedingCalculator(new NonHeadToHeadTiebreaker());
    }

    /**
     * Build a minimal StandingsRow for seeding tests.
     * @return StandingsRow
     */
    private function row(int $teamid, string $name, int $wins, int $losses, string $division = 'Atlantic'): array
    {
        $total = $wins + $losses;
        return [
            'teamid' => $teamid,
            'team_name' => $name,
            'wins' => $wins,
            'losses' => $losses,
            'pct' => $total > 0 ? $wins / $total : 0.0,
            'conference' => 'Eastern',
            'division' => $division,
            'conf_wins' => null,
            'conf_losses' => null,
            'div_wins' => null,
            'div_losses' => null,
            'clinched_division' => null,
            'color1' => 'AA0001',
            'color2' => 'BB0001',
        ];
    }

    /**
     * @param list<StandingsRow> $teams
     * @return list<int>
     */
    private function ids(array $teams): array
    {
        return array_column($teams, 'teamid');
    }

    // ── Empty conference ──────────────────────────────────────────────────

    public function testEmptyConferenceReturnsEmptyBuckets(): void
    {
        $result = $this->calculator->determinePlayoffTeams([], [], []);

        $this->assertSame(
            ['wildCards' => [], 'divisionWinners' => [], 'conferenceWinner' => null, 'nonPlayoff' => []],
            $result
        );
    }

    // ── Full conference: 6 wild cards + division winners + non-playoff ────

    public function testFullConferenceSplit(): void
    {
        // Atlantic: teamids 1-7, wins descending (70,60,50,40,30,20,10)
        // Central:  teamids 8-14, wins descending (65,55,45,35,25,15,5)
        $atlantic = [
            $this->row(1, 'A1', 70, 12),
            $this->row(2, 'A2', 60, 22),
            $this->row(3, 'A3', 50, 32),
            $this->row(4, 'A4', 40, 42),
            $this->row(5, 'A5', 30, 52),
            $this->row(6, 'A6', 20, 62),
            $this->row(7, 'A7', 10, 72),
        ];
        $central = [
            $this->row(8, 'C1', 65, 17, 'Central'),
            $this->row(9, 'C2', 55, 27, 'Central'),
            $this->row(10, 'C3', 45, 37, 'Central'),
            $this->row(11, 'C4', 35, 47, 'Central'),
            $this->row(12, 'C5', 25, 57, 'Central'),
            $this->row(13, 'C6', 15, 67, 'Central'),
            $this->row(14, 'C7', 5, 77, 'Central'),
        ];
        $conference = array_merge($atlantic, $central);

        $result = $this->calculator->determinePlayoffTeams($conference, [], []);

        // Conference winner: team1 (70W best overall)
        $this->assertSame(1, $result['conferenceWinner']['teamid']);

        // Non-conf division winner: Central winner team8
        $this->assertSame([8], $this->ids($result['divisionWinners']));

        // Wild cards: top-6 non-division-winners sorted best first
        $this->assertSame([2, 9, 3, 10, 4, 11], $this->ids($result['wildCards']));

        // Non-playoff: remaining 6 non-winners, worst-first in the sorted list
        $this->assertSame([5, 12, 6, 13, 7, 14], $this->ids($result['nonPlayoff']));
    }

    // ── Pairwise H2H decides tied division winner (applyTiebreakers path) ─

    public function testH2HTiebreakerDecidesDivisionWinner(): void
    {
        // Two teams in same division with equal pct, team1 beat team2 once.
        // applyTiebreakers: aWinsVsB=1 > bWinsVsA=0 → return bWinsVsA <=> aWinsVsB = -1 → team1 wins.
        $team1 = $this->row(1, 'Team1', 41, 41);
        $team2 = $this->row(2, 'Team2', 41, 41);
        $h2h = [1 => [2 => 1], 2 => [1 => 0]];

        $result = $this->calculator->determinePlayoffTeams([$team1, $team2], $h2h, []);

        $this->assertSame(1, $result['conferenceWinner']['teamid']);
        $this->assertSame([2], $this->ids($result['wildCards']));
    }

    // ── pct-based seeding (compareTeamsForPlayoffSeeding pct branch) ──────

    public function testHigherPctTeamBecomesConferenceWinner(): void
    {
        // Two divisions. Best overall record wins the conference.
        $atlantic = [
            $this->row(1, 'A1', 70, 12),
            $this->row(2, 'A2', 30, 52),
        ];
        $central = [
            $this->row(3, 'C1', 65, 17, 'Central'),
            $this->row(4, 'C2', 25, 57, 'Central'),
        ];

        $result = $this->calculator->determinePlayoffTeams(array_merge($atlantic, $central), [], []);

        $this->assertSame(1, $result['conferenceWinner']['teamid']);
        $this->assertSame([3], $this->ids($result['divisionWinners']));
    }
}
