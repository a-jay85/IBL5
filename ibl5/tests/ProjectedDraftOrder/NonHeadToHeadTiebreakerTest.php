<?php

declare(strict_types=1);

namespace Tests\ProjectedDraftOrder;

use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;
use ProjectedDraftOrder\NonHeadToHeadTiebreaker;

/**
 * @covers \ProjectedDraftOrder\NonHeadToHeadTiebreaker
 * @phpstan-import-type StandingsRow from ProjectedDraftOrderRepositoryInterface
 */
class NonHeadToHeadTiebreakerTest extends TestCase
{
    private NonHeadToHeadTiebreaker $tiebreaker;

    protected function setUp(): void
    {
        $this->tiebreaker = new NonHeadToHeadTiebreaker();
    }

    /**
     * Build a minimal StandingsRow for tiebreaker tests.
     * @return StandingsRow
     */
    private function row(
        int $teamid,
        string $name,
        string $division = 'Atlantic',
        string $conference = 'Eastern',
        ?int $clinchedDiv = null,
        ?int $divWins = null,
        ?int $divLosses = null,
        ?int $confWins = null,
        ?int $confLosses = null,
    ): array {
        return [
            'teamid' => $teamid,
            'team_name' => $name,
            'wins' => 41,
            'losses' => 41,
            'pct' => 0.5,
            'conference' => $conference,
            'division' => $division,
            'conf_wins' => $confWins,
            'conf_losses' => $confLosses,
            'div_wins' => $divWins,
            'div_losses' => $divLosses,
            'clinched_division' => $clinchedDiv,
            'color1' => 'AA0001',
            'color2' => 'BB0001',
        ];
    }

    // ── Arm 2: Division winner status ─────────────────────────────────────

    public function testDivisionWinnerStatusAWinsReturnsNegative(): void
    {
        $a = $this->row(1, 'Team A', clinchedDiv: 1);
        $b = $this->row(2, 'Team B', clinchedDiv: null);

        $result = $this->tiebreaker->applyNonH2HTiebreakers($a, $b, []);

        $this->assertLessThan(0, $result);
    }

    public function testDivisionWinnerStatusBWinsReturnsPositive(): void
    {
        $a = $this->row(1, 'Team A', clinchedDiv: null);
        $b = $this->row(2, 'Team B', clinchedDiv: 1);

        $result = $this->tiebreaker->applyNonH2HTiebreakers($a, $b, []);

        $this->assertGreaterThan(0, $result);
    }

    // ── Arm 3: Division record (same division only) ────────────────────────

    public function testDivisionRecordABetterReturnsNegative(): void
    {
        $a = $this->row(1, 'Team A', divWins: 10, divLosses: 2);
        $b = $this->row(2, 'Team B', divWins: 2, divLosses: 10);

        $result = $this->tiebreaker->applyNonH2HTiebreakers($a, $b, []);

        $this->assertLessThan(0, $result);
    }

    public function testDivisionRecordSkippedForDifferentDivisions(): void
    {
        // Different divisions — div record arm must NOT fire even if div records differ.
        // Falls through to conf record (both null → 0.0 tie) → alphabetical.
        $a = $this->row(1, 'Team A', division: 'Atlantic', divWins: 10, divLosses: 0);
        $b = $this->row(2, 'Team B', division: 'Central', divWins: 0, divLosses: 10);

        $result = $this->tiebreaker->applyNonH2HTiebreakers($a, $b, []);

        // Alphabetical: 'Team A' < 'Team B' → returns negative
        $this->assertLessThan(0, $result);
    }

    // ── Arm 4: Conference record ──────────────────────────────────────────

    public function testConferenceRecordABetterReturnsNegative(): void
    {
        $a = $this->row(1, 'Team A', division: 'Atlantic', confWins: 30, confLosses: 10);
        $b = $this->row(2, 'Team B', division: 'Atlantic', confWins: 10, confLosses: 30);

        $result = $this->tiebreaker->applyNonH2HTiebreakers($a, $b, []);

        $this->assertLessThan(0, $result);
    }

    // ── safeWinPct zero-total boundary (via conf record arm) ──────────────

    public function testSafeWinPctZeroTotalFallsThroughToAlphabetical(): void
    {
        // Both conf_wins=null, conf_losses=null → safeWinPct returns 0.0 for both.
        // Conf record arm does not decide → falls to alphabetical.
        $a = $this->row(1, 'Team A', division: 'Atlantic');
        $b = $this->row(2, 'Team B', division: 'Atlantic');

        $result = $this->tiebreaker->applyNonH2HTiebreakers($a, $b, []);

        $this->assertLessThan(0, $result);
    }

    // ── Arm 5: Point differential ─────────────────────────────────────────

    public function testPointDiffABetterReturnsNegative(): void
    {
        $a = $this->row(1, 'Team A');
        $b = $this->row(2, 'Team B');

        $pointDiffs = [1 => 50.0, 2 => -50.0];
        $result = $this->tiebreaker->applyNonH2HTiebreakers($a, $b, $pointDiffs);

        $this->assertLessThan(0, $result);
    }

    // ── Fallback: alphabetical ────────────────────────────────────────────

    public function testAlphabeticalFallbackAFirstReturnsNegative(): void
    {
        $a = $this->row(1, 'Alpha');
        $b = $this->row(2, 'Zeta');

        $result = $this->tiebreaker->applyNonH2HTiebreakers($a, $b, []);

        $this->assertLessThan(0, $result);
    }

    public function testAlphabeticalFallbackBFirstReturnsPositive(): void
    {
        $a = $this->row(1, 'Zeta');
        $b = $this->row(2, 'Alpha');

        $result = $this->tiebreaker->applyNonH2HTiebreakers($a, $b, []);

        $this->assertGreaterThan(0, $result);
    }
}
