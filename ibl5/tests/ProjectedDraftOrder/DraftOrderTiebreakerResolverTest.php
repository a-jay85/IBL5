<?php

declare(strict_types=1);

namespace Tests\ProjectedDraftOrder;

use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;
use ProjectedDraftOrder\DraftOrderTiebreakerResolver;
use ProjectedDraftOrder\NonHeadToHeadTiebreaker;

/**
 * @covers \ProjectedDraftOrder\DraftOrderTiebreakerResolver
 * @phpstan-import-type StandingsRow from ProjectedDraftOrderRepositoryInterface
 */
class DraftOrderTiebreakerResolverTest extends TestCase
{
    private DraftOrderTiebreakerResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DraftOrderTiebreakerResolver(new NonHeadToHeadTiebreaker());
    }

    /**
     * Build a minimal StandingsRow for sorting tests.
     * @return StandingsRow
     */
    private function row(int $teamid, string $name, int $wins, int $losses): array
    {
        $total = $wins + $losses;
        return [
            'teamid' => $teamid,
            'team_name' => $name,
            'wins' => $wins,
            'losses' => $losses,
            'pct' => $total > 0 ? $wins / $total : 0.0,
            'conference' => 'Eastern',
            'division' => 'Atlantic',
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

    // ── Boundary cases ────────────────────────────────────────────────────

    public function testEmptyListReturnsEmpty(): void
    {
        $this->assertSame([], $this->resolver->sortTeamsByRecord([], [], []));
    }

    public function testSingleTeamReturnedUnchanged(): void
    {
        $team = $this->row(1, 'Solo', 40, 42);

        $result = $this->resolver->sortTeamsByRecord([$team], [], []);

        $this->assertSame([1], $this->ids($result));
    }

    // ── pct ordering ──────────────────────────────────────────────────────

    public function testDistinctPctsWorstFirst(): void
    {
        $teams = [
            $this->row(1, 'Best', 60, 22),
            $this->row(2, 'Mid', 41, 41),
            $this->row(3, 'Worst', 20, 62),
        ];

        $result = $this->resolver->sortTeamsByRecord($teams, [], []);

        $this->assertSame([3, 2, 1], $this->ids($result));
    }

    // ── Tied group: H2H decides ───────────────────────────────────────────

    public function testSamePctH2HWorseAggregateSortedFirst(): void
    {
        // Team 1 beat Team 2: aggregate H2H pct — team1=1.0, team2=0.0.
        // Draft order: worse H2H aggregate (lower pct) gets earlier pick.
        $teams = [
            $this->row(1, 'Team1', 41, 41),
            $this->row(2, 'Team2', 41, 41),
        ];
        $h2h = [1 => [2 => 1], 2 => [1 => 0]];

        $result = $this->resolver->sortTeamsByRecord($teams, $h2h, []);

        $this->assertSame([2, 1], $this->ids($result));
    }

    // ── 3-way tied group with cyclic H2H ──────────────────────────────────

    public function testThreeWaySamePctCyclicH2HAlphabeticalSignInverted(): void
    {
        // Cyclic H2H: A→B, B→C, C→A. All aggregate pcts = 0.5.
        // Falls through to sign-inverted alphabetical: 'TeamC' first, 'TeamA' last.
        $teams = [
            $this->row(1, 'TeamA', 41, 41),
            $this->row(2, 'TeamB', 41, 41),
            $this->row(3, 'TeamC', 41, 41),
        ];
        $h2h = [
            1 => [2 => 1, 3 => 0],
            2 => [1 => 0, 3 => 1],
            3 => [1 => 1, 2 => 0],
        ];

        $result = $this->resolver->sortTeamsByRecord($teams, $h2h, []);

        $this->assertSame([3, 2, 1], $this->ids($result));
    }

    // ── Tied group: empty H2H, sign-inverted alphabetical fallback ─────────

    public function testSamePctNoH2HSignInvertedAlphabetical(): void
    {
        // No games played — aggregate H2H pcts both 0.0, ties → non-H2H tail.
        // Sign-inverted alphabetical: alphabetically later team sorts first.
        $teams = [
            $this->row(1, 'TeamA', 41, 41),
            $this->row(2, 'TeamB', 41, 41),
        ];

        $result = $this->resolver->sortTeamsByRecord($teams, [], []);

        $this->assertSame([2, 1], $this->ids($result));
    }
}
