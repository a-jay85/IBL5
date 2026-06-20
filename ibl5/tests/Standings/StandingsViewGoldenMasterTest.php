<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use SeriesRecords\SeriesRecordsService;
use Standings\StandingsView;
use Standings\Contracts\StandingsRepositoryInterface;

/**
 * Golden-master pin + divergence-guard for StandingsView.
 *
 * Captures EXACT current output of both render paths — inconsistency and all —
 * before the ensureBulkDataLoaded() refactor, then verifies byte-identity after.
 *
 * The deliberate sort inconsistency between render() (multi-key PHP sort) and
 * renderRegion() (SQL-order trust) is PRESERVED. Fixing it is DEFERRED to a
 * separate future plan.
 *
 * @covers \Standings\StandingsView
 */
#[AllowMockObjectsWithoutExpectations]
class StandingsViewGoldenMasterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Divergence fixture helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> BulkStandingsRow for Team A (teamid=1, clinched_division=1, clinch tier=2) */
    private function bulkTeamA(): array
    {
        return [
            'teamid' => 1,
            'team_name' => 'Team Alpha',
            'league_record' => '50-32',
            'pct' => '0.610',
            'conf_gb' => '0.0',
            'div_gb' => '0.0',
            'conf_magic_number' => 0,
            'div_magic_number' => 0,
            'games_unplayed' => 0,
            'conf_record' => '30-16',
            'div_record' => '10-4',
            'home_record' => '28-14',
            'away_record' => '22-18',
            'homeGames' => 42,
            'awayGames' => 40,
            'clinched_conference' => 0,
            'clinched_division' => 1,
            'clinched_playoffs' => 0,
            'clinched_league' => 0,
            'wins' => 50,
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'color1' => '000080',
            'color2' => 'FFFFFF',
        ];
    }

    /** @return array<string, mixed> BulkStandingsRow for Team B (teamid=2, clinched_playoffs=1, clinch tier=1) */
    private function bulkTeamB(): array
    {
        return [
            'teamid' => 2,
            'team_name' => 'Team Beta',
            'league_record' => '50-32',
            'pct' => '0.610',
            'conf_gb' => '0.0',
            'div_gb' => '0.0',
            'conf_magic_number' => 0,
            'div_magic_number' => 0,
            'games_unplayed' => 0,
            'conf_record' => '30-16',
            'div_record' => '10-4',
            'home_record' => '28-14',
            'away_record' => '22-18',
            'homeGames' => 42,
            'awayGames' => 40,
            'clinched_conference' => 0,
            'clinched_division' => 0,
            'clinched_playoffs' => 1,
            'clinched_league' => 0,
            'wins' => 50,
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'color1' => '800000',
            'color2' => 'FFFFFF',
        ];
    }

    /** @return array<string, mixed> StandingsRow for Team A (teamid=1, clinched_division=1) */
    private function regionTeamA(): array
    {
        return [
            'teamid' => 1,
            'team_name' => 'Team Alpha',
            'league_record' => '50-32',
            'pct' => '0.610',
            'gamesBack' => '0.0',
            'magicNumber' => 0,
            'games_unplayed' => 0,
            'conf_record' => '30-16',
            'div_record' => '10-4',
            'home_record' => '28-14',
            'away_record' => '22-18',
            'homeGames' => 42,
            'awayGames' => 40,
            'clinched_conference' => 0,
            'clinched_division' => 1,
            'clinched_playoffs' => 0,
            'clinched_league' => 0,
            'wins' => 50,
            'color1' => '000080',
            'color2' => 'FFFFFF',
        ];
    }

    /** @return array<string, mixed> StandingsRow for Team B (teamid=2, clinched_playoffs=1) */
    private function regionTeamB(): array
    {
        return [
            'teamid' => 2,
            'team_name' => 'Team Beta',
            'league_record' => '50-32',
            'pct' => '0.610',
            'gamesBack' => '0.0',
            'magicNumber' => 0,
            'games_unplayed' => 0,
            'conf_record' => '30-16',
            'div_record' => '10-4',
            'home_record' => '28-14',
            'away_record' => '22-18',
            'homeGames' => 42,
            'awayGames' => 40,
            'clinched_conference' => 0,
            'clinched_division' => 0,
            'clinched_playoffs' => 1,
            'clinched_league' => 0,
            'wins' => 50,
            'color1' => '800000',
            'color2' => 'FFFFFF',
        ];
    }

    /**
     * Build a fresh StandingsView with the divergence fixture wired in.
     *
     * @param list<array<string, mixed>> $regionEasternRows Rows getStandingsByRegion('Eastern') returns
     */
    private function buildView(array $regionEasternRows = []): StandingsView
    {
        $repo = $this->createMock(StandingsRepositoryInterface::class);
        $repo->method('getAllStandings')->willReturn([$this->bulkTeamA(), $this->bulkTeamB()]);
        $repo->method('getAllStreakData')->willReturn([]);
        $repo->method('getAllPythagoreanStats')->willReturn([]);
        $repo->method('getSeriesRecords')->willReturn([]);
        $repo->method('getStandingsByRegion')->willReturnCallback(
            static fn (string $region): array => $region === 'Eastern' ? $regionEasternRows : []
        );

        return new StandingsView($repo, 2025, new SeriesRecordsService());
    }

    /**
     * Snapshot helper — writes on first run; compares on subsequent runs.
     *
     * On the first run (no snapshot file yet) the snapshot is auto-created from
     * $actual and the test passes. Commit the generated files before editing
     * StandingsView.php so that later runs catch any divergence.
     */
    private function assertSnapshotMatches(string $actual, string $snapshotFilename): void
    {
        $snapshotDir = __DIR__ . '/__snapshots__';
        $path = $snapshotDir . '/' . $snapshotFilename;

        if (!file_exists($path)) {
            if (!is_dir($snapshotDir)) {
                mkdir($snapshotDir, 0755, true);
            }
            file_put_contents($path, $actual);
            $this->addToAssertionCount(1);
            return;
        }

        $expected = file_get_contents($path);
        $this->assertSame($expected, $actual, "Golden master mismatch for $snapshotFilename");
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function testRenderFullOutputMatchesGoldenMaster(): void
    {
        $view = $this->buildView();
        $this->assertSnapshotMatches($view->render(), 'render-full.html');
    }

    public function testRenderRegionEasternMatchesGoldenMaster(): void
    {
        // SQL-trust order: [team2, team1] — lower clinch tier first (mirrors raw ORDER BY conf_gb ASC)
        $view = $this->buildView([$this->regionTeamB(), $this->regionTeamA()]);
        $this->assertSnapshotMatches($view->renderRegion('Eastern'), 'renderRegion-eastern.html');
    }

    /**
     * Divergence / collapse-guard.
     *
     * render() applies multi-key PHP sortStandings() — clinch-DESC is key #2,
     * so team1 (clinched_division=1, score=2) precedes team2 (clinched_playoffs=1, score=1).
     *
     * renderRegion() trusts the SQL-supplied order [team2, team1] unchanged
     * (only H2H tie-breaking via resolveH2HTiedGroups, which is a no-op here
     * because seriesMatrix is empty).
     *
     * This test goes RED if anyone routes renderRegion() through sortStandings()
     * or adaptBulkRows() — the forbidden path convergence.
     */
    public function testRenderAndRenderRegionDivergeInRowOrder(): void
    {
        // Fresh view for render()
        $renderView = $this->buildView();
        $renderHtml = $renderView->render();

        // Fresh view for renderRegion() — SQL order is [team2, team1]
        $regionView = $this->buildView([$this->regionTeamB(), $this->regionTeamA()]);
        $regionHtml = $regionView->renderRegion('Eastern');

        // Slice the Eastern Conference block from the full render() output.
        // Atlantic Division ALSO contains these two teams, so we must not scan the full HTML.
        $easternStart = strpos($renderHtml, 'Eastern Conference');
        $this->assertNotFalse($easternStart, 'Eastern Conference heading not found in render() output');

        $nextH2Pos = strpos($renderHtml, '<h2', $easternStart + 1);
        $easternBlock = $nextH2Pos !== false
            ? substr($renderHtml, $easternStart, $nextH2Pos - $easternStart)
            : substr($renderHtml, $easternStart);

        preg_match_all('/data-team-id="(\d+)"/', $easternBlock, $renderMatches);
        $renderOrder = array_map('intval', $renderMatches[1]);

        preg_match_all('/data-team-id="(\d+)"/', $regionHtml, $regionMatches);
        $regionOrder = array_map('intval', $regionMatches[1]);

        // render() — clinch-DESC: team1 (clinched_division, tier=2) before team2 (clinched_playoffs, tier=1)
        $this->assertSame([1, 2], $renderOrder, 'render() Eastern must emit clinch-DESC order [1, 2]');
        // renderRegion() — trusts SQL order supplied by mock: [team2, team1]
        $this->assertSame([2, 1], $regionOrder, 'renderRegion() Eastern must emit SQL-trust order [2, 1]');
        // Collapse guard — paths still diverge after the refactor
        $this->assertNotSame($renderOrder, $regionOrder, 'Divergence guard: render() and renderRegion() must produce different Eastern row orders');
    }
}
