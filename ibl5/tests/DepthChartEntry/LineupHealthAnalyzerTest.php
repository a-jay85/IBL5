<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use DepthChartEntry\LineupHealthAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the pure-function LineupHealthAnalyzer.
 *
 * Every case passes plain arrays — no DB, no superglobals — which is exactly
 * what makes owner-scoping structural (matrix rows 13/14): the analyzer can
 * only ever see the roster the controller injects.
 */
final class LineupHealthAnalyzerTest extends TestCase
{
    private LineupHealthAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new LineupHealthAnalyzer();
    }

    /**
     * Build a roster row carrying only the fields the analyzer reads.
     *
     * @param array<string, int|string> $overrides
     * @return array<string, int|string>
     */
    private function makePlayer(string $name, array $overrides = []): array
    {
        return array_merge([
            'name' => $name,
            'injured' => 0,
            'dc_can_play_in_game' => 1,
            'dc_pg_depth' => 0,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 0,
        ], $overrides);
    }

    /**
     * A fully valid roster: 5 distinct starters, exactly 3 players assigned at
     * each of PG/SG/SF/PF/C, none injured, all active. Each player covers three
     * positions so every slot has a 1st/2nd/3rd.
     *
     * @return list<array<string, int|string>>
     */
    private function validRoster(): array
    {
        return [
            $this->makePlayer('Alpha', ['dc_pg_depth' => 1, 'dc_sg_depth' => 2, 'dc_sf_depth' => 3]),
            $this->makePlayer('Bravo', ['dc_sg_depth' => 1, 'dc_sf_depth' => 2, 'dc_pf_depth' => 3]),
            $this->makePlayer('Charlie', ['dc_sf_depth' => 1, 'dc_pf_depth' => 2, 'dc_c_depth' => 3]),
            $this->makePlayer('Delta', ['dc_pf_depth' => 1, 'dc_c_depth' => 2, 'dc_pg_depth' => 3]),
            $this->makePlayer('Echo', ['dc_c_depth' => 1, 'dc_pg_depth' => 2, 'dc_sg_depth' => 3]),
        ];
    }

    /**
     * @param list<array{type: string, message: string}> $warnings
     * @return list<array{type: string, message: string}>
     */
    private function ofType(array $warnings, string $type): array
    {
        return array_values(array_filter($warnings, static fn(array $w): bool => $w['type'] === $type));
    }

    public function testValidRosterReturnsNoWarnings(): void
    {
        $this->assertSame([], $this->analyzer->analyze($this->validRoster(), 4999));
    }

    public function testNoStarterFiresForUnstaffedPosition(): void
    {
        $roster = $this->validRoster();
        // Demote Echo's C from starter to a backup; C keeps 3 assigned players
        // (Charlie 3rd, Delta 2nd, Echo 2nd) so ONLY no_starter fires, not thin_depth.
        $roster[4]['dc_c_depth'] = 2;

        $warnings = $this->analyzer->analyze($roster, 4999);
        $noStarter = $this->ofType($warnings, 'no_starter');

        $this->assertCount(1, $noStarter);
        $this->assertStringContainsString('C', $noStarter[0]['message']);
        $this->assertSame([], $this->ofType($warnings, 'thin_depth'));
    }

    public function testThinDepthDoesNotFireAtBoundaryOfThree(): void
    {
        // validRoster has exactly 3 assigned at every position → no thin_depth.
        $this->assertSame([], $this->ofType($this->analyzer->analyze($this->validRoster(), 4999), 'thin_depth'));
    }

    public function testThinDepthFiresWhenOnlyTwoAssigned(): void
    {
        $roster = [
            $this->makePlayer('One', ['dc_pg_depth' => 1]),
            $this->makePlayer('Two', ['dc_pg_depth' => 2]),
        ];

        $thin = $this->ofType($this->analyzer->analyze($roster, 0), 'thin_depth');
        $pgThin = array_filter($thin, static fn(array $w): bool => str_contains($w['message'], 'PG'));

        $this->assertCount(1, $pgThin, 'PG with only 2 assigned should fire thin_depth');
    }

    public function testInjuredStarterFiresAndNamesPlayer(): void
    {
        $roster = [$this->makePlayer('Hurt Guy', ['dc_pg_depth' => 1, 'injured' => 4])];

        $injured = $this->ofType($this->analyzer->analyze($roster, 0), 'injured_starter');

        $this->assertCount(1, $injured);
        $this->assertStringContainsString('Hurt Guy', $injured[0]['message']);
        $this->assertStringContainsString('PG', $injured[0]['message']);
    }

    public function testInactiveStarterFiresAndNamesPlayer(): void
    {
        $roster = [$this->makePlayer('Benched Guy', ['dc_sf_depth' => 1, 'dc_can_play_in_game' => 0])];

        $inactive = $this->ofType($this->analyzer->analyze($roster, 0), 'inactive_starter');

        $this->assertCount(1, $inactive);
        $this->assertStringContainsString('Benched Guy', $inactive[0]['message']);
        $this->assertStringContainsString('SF', $inactive[0]['message']);
    }

    public function testOverCapFiresStrictlyAboveSoftCap(): void
    {
        $this->assertCount(1, $this->ofType($this->analyzer->analyze([], 5001), 'over_cap'));
    }

    public function testOverCapDoesNotFireAtSoftCapBoundary(): void
    {
        $this->assertSame([], $this->ofType($this->analyzer->analyze([], 5000), 'over_cap'));
    }

    public function testStarterWithOnlyTwoPlayersFiresThinDepthNotNoStarter(): void
    {
        // PG has a starter (One at depth 1) but only 2 players assigned.
        $roster = [
            $this->makePlayer('One', ['dc_pg_depth' => 1]),
            $this->makePlayer('Two', ['dc_pg_depth' => 2]),
        ];

        $warnings = $this->analyzer->analyze($roster, 0);

        $pgNoStarter = array_filter(
            $this->ofType($warnings, 'no_starter'),
            static fn(array $w): bool => str_contains($w['message'], 'PG')
        );
        $pgThin = array_filter(
            $this->ofType($warnings, 'thin_depth'),
            static fn(array $w): bool => str_contains($w['message'], 'PG')
        );

        $this->assertCount(0, $pgNoStarter, 'PG has a starter → no_starter must NOT fire for PG');
        $this->assertCount(1, $pgThin, 'PG has only 2 assigned → thin_depth must fire for PG');
    }

    public function testWarningOrderIsStableByType(): void
    {
        // One roster that trips every check; assert the type sequence is the
        // documented order: no_starter, thin_depth, injured_starter,
        // inactive_starter, over_cap.
        $roster = [
            $this->makePlayer('Starter', ['dc_pg_depth' => 1, 'injured' => 2, 'dc_can_play_in_game' => 0]),
        ];

        $types = array_values(array_unique(array_map(
            static fn(array $w): string => $w['type'],
            $this->analyzer->analyze($roster, 6000)
        )));

        $this->assertSame(
            ['no_starter', 'thin_depth', 'injured_starter', 'inactive_starter', 'over_cap'],
            $types
        );
    }

    public function testAnalyzeIgnoresSuperglobals(): void
    {
        // Structural owner-scoping (matrix row 14): analyze() reads only its
        // arguments, never $_REQUEST/$_GET/$_POST/$_COOKIE.
        $_REQUEST['teamid'] = 999;
        $_GET['injected'] = '<script>';
        $_POST['evil'] = 1;

        $result = $this->analyzer->analyze($this->validRoster(), 4999);

        unset($_REQUEST['teamid'], $_GET['injected'], $_POST['evil']);

        $this->assertSame([], $result);
    }
}
