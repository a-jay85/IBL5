<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use SeriesRecords\SeriesRecordsService;
use Standings\StandingsRowView;
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
 * @covers \Standings\StandingsTiebreakerResolver
 * @phpstan-import-type StandingsRow from \Standings\Contracts\StandingsRepositoryInterface
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

    /** @return StandingsRow Team A (teamid=1), tied on GB/wins/clinch-tier — for H2H tiebreak tests */
    private function tiedTeamA(): array
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
            'clinched_division' => 0,
            'clinched_playoffs' => 0,
            'clinched_league' => 0,
            'wins' => 50,
            'color1' => '000080',
            'color2' => 'FFFFFF',
        ];
    }

    /** @return StandingsRow Team B (teamid=2), tied on GB/wins/clinch-tier — for H2H tiebreak tests */
    private function tiedTeamB(): array
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
            'clinched_playoffs' => 0,
            'clinched_league' => 0,
            'wins' => 50,
            'color1' => '800000',
            'color2' => 'FFFFFF',
        ];
    }

    /** @return StandingsRow Team C (teamid=3), tied on GB/wins/clinch-tier — for H2H tiebreak tests */
    private function tiedTeamC(): array
    {
        return [
            'teamid' => 3,
            'team_name' => 'Team Charlie',
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
            'clinched_playoffs' => 0,
            'clinched_league' => 0,
            'wins' => 50,
            'color1' => '008000',
            'color2' => 'FFFFFF',
        ];
    }

    /**
     * Build a fresh StandingsView with the divergence fixture wired in.
     *
     * @param list<array<string, mixed>> $regionEasternRows Rows getStandingsByRegion('Eastern') returns
     * @param list<array{self: int, opponent: int, wins: int, losses: int}> $seriesRecords Series records for H2H matrix
     * @param list<array<string, mixed>>|null $bulkRows Override for getAllStandings() (null = default two-team fixture)
     */
    private function buildView(
        array $regionEasternRows = [],
        array $seriesRecords = [],
        ?array $bulkRows = null
    ): StandingsView {
        $repo = $this->createMock(StandingsRepositoryInterface::class);
        $repo->method('getAllStandings')->willReturn($bulkRows ?? [$this->bulkTeamA(), $this->bulkTeamB()]);
        $repo->method('getAllStreakData')->willReturn([]);
        $repo->method('getAllPythagoreanStats')->willReturn([]);
        $repo->method('getSeriesRecords')->willReturn($seriesRecords);
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
            $this->assertFileExists($path, "Snapshot $snapshotFilename was not created");
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
        // @phpstan-ignore method.alreadyNarrowedType (PHPStan narrows these to literal arrays after assertSame; the runtime divergence is what matters here)
        $this->assertNotSame($renderOrder, $regionOrder, 'Divergence guard: render() and renderRegion() must produce different Eastern row orders');
    }

    public function testRenderRegionAppliesH2HTiebreakToTiedGroup(): void
    {
        // Three teams genuinely tied: same gamesBack='0.0', same wins=50, same clinch tier (all 0).
        // H2H records: C beats A 3-0, C beats B 3-0, A beats B 2-1.
        // Aggregate H2H pcts: C=1.000, A=0.333, B=0.167 → strict total order → [3, 1, 2].
        $seriesRecords = [
            ['self' => 3, 'opponent' => 1, 'wins' => 3, 'losses' => 0],
            ['self' => 1, 'opponent' => 3, 'wins' => 0, 'losses' => 3],
            ['self' => 3, 'opponent' => 2, 'wins' => 3, 'losses' => 0],
            ['self' => 2, 'opponent' => 3, 'wins' => 0, 'losses' => 3],
            ['self' => 1, 'opponent' => 2, 'wins' => 2, 'losses' => 1],
            ['self' => 2, 'opponent' => 1, 'wins' => 1, 'losses' => 2],
        ];

        // SQL order is [A=1, B=2, C=3]; H2H tiebreaker must reorder to [C=3, A=1, B=2]
        $view = $this->buildView(
            [$this->tiedTeamA(), $this->tiedTeamB(), $this->tiedTeamC()],
            $seriesRecords
        );
        $html = $view->renderRegion('Eastern');

        preg_match_all('/data-team-id="(\d+)"/', $html, $matches);
        $teamOrder = array_map('intval', $matches[1]);

        $this->assertSame([3, 1, 2], $teamOrder, 'H2H tiebreaker must place C (best aggregate record) first');
        $this->assertSnapshotMatches($html, 'renderRegion-h2h-tiebreak.html');
    }

    public function testRenderRegionLeavesUntiedTeamsInSqlOrder(): void
    {
        // Teams share gamesBack='0.0' and clinch tier 0, but differ on wins — not a tied group.
        // resolveH2HTiedGroups() is a no-op per group, so SQL order is preserved.
        $seriesRecords = [
            ['self' => 3, 'opponent' => 1, 'wins' => 3, 'losses' => 0],
            ['self' => 1, 'opponent' => 3, 'wins' => 0, 'losses' => 3],
            ['self' => 3, 'opponent' => 2, 'wins' => 3, 'losses' => 0],
            ['self' => 2, 'opponent' => 3, 'wins' => 0, 'losses' => 3],
            ['self' => 1, 'opponent' => 2, 'wins' => 2, 'losses' => 1],
            ['self' => 2, 'opponent' => 1, 'wins' => 1, 'losses' => 2],
        ];

        $teamB49 = array_merge($this->tiedTeamB(), ['wins' => 49]);
        $teamC48 = array_merge($this->tiedTeamC(), ['wins' => 48]);

        $view = $this->buildView(
            [$this->tiedTeamA(), $teamB49, $teamC48],
            $seriesRecords
        );
        $html = $view->renderRegion('Eastern');

        preg_match_all('/data-team-id="(\d+)"/', $html, $matches);
        $teamOrder = array_map('intval', $matches[1]);

        $this->assertSame([1, 2, 3], $teamOrder, 'Untied teams must stay in SQL-supplied order');
    }

    public function testRenderRegionMarksBottomLockedRowsMidSeason(): void
    {
        // Mid-season: Team A has games_unplayed=10, so isSeasonOver()=false → cascade branch runs.
        // Team B: wins(30) + games_unplayed(5) = 35 < wins(50) of Team A → bottom-locked.
        $teamA = array_merge($this->tiedTeamA(), ['wins' => 50, 'games_unplayed' => 10, 'gamesBack' => '0.0']);
        $teamB = array_merge($this->tiedTeamB(), ['wins' => 30, 'games_unplayed' => 5, 'gamesBack' => '20.0']);

        $view = $this->buildView([$teamA, $teamB]);
        $html = $view->renderRegion('Eastern');

        $this->assertStringContainsString('bottom-locked', $html, 'Trailing team must be marked bottom-locked');
        $this->assertSnapshotMatches($html, 'renderRegion-bottom-locked.html');
    }

    public function testRenderRegionMarksNoRowsLockedWhenLeaderCatchable(): void
    {
        // Team B: wins(41) + games_unplayed(9) = 50, which equals Team A's wins(50).
        // The lock condition is strict <, so equality means catchable → no bottom-locked row.
        $teamA = array_merge($this->tiedTeamA(), ['wins' => 50, 'games_unplayed' => 10, 'gamesBack' => '0.0']);
        $teamB = array_merge($this->tiedTeamB(), ['wins' => 41, 'games_unplayed' => 9, 'gamesBack' => '9.0']);

        $view = $this->buildView([$teamA, $teamB]);
        $html = $view->renderRegion('Eastern');

        $this->assertStringNotContainsString('bottom-locked', $html, 'No team should be bottom-locked when leader is catchable');
    }

    public function testRenderRegionEmitsEveryClinchTierClass(): void
    {
        // Four teams, one per clinch tier. All games_unplayed=0 so isSeasonOver()=true.
        // Every team must be clinched or the season-over branch marks it bottom-locked instead.
        $leagueTeam   = array_merge($this->tiedTeamA(), ['clinched_league' => 1]);
        $confTeam     = array_merge($this->tiedTeamB(), ['teamid' => 2, 'clinched_conference' => 1]);
        $divTeam      = array_merge($this->tiedTeamC(), ['teamid' => 3, 'clinched_division' => 1]);
        $playoffTeam  = array_merge($this->tiedTeamC(), [
            'teamid' => 4,
            'team_name' => 'Team Delta',
            'color1' => '4B0082',
            'clinched_playoffs' => 1,
        ]);

        $view = $this->buildView([$leagueTeam, $confTeam, $divTeam, $playoffTeam]);
        $html = $view->renderRegion('Eastern');

        $this->assertStringContainsString('clinch-league', $html, 'clinch-league class must be present');
        $this->assertStringContainsString('clinch-conference', $html, 'clinch-conference class must be present');
        $this->assertStringContainsString('clinch-division', $html, 'clinch-division class must be present');
        $this->assertStringContainsString('clinch-playoffs', $html, 'clinch-playoffs class must be present');
        $this->assertSnapshotMatches($html, 'renderRegion-clinch-tiers.html');
    }

    public function testRenderRegionWithNoTeamsStillEmitsTableStructure(): void
    {
        // buildView() with no args returns [] for Western; renderRegion must still emit a well-formed table.
        $view = $this->buildView();
        $html = $view->renderRegion('Western');

        $this->assertNotEmpty($html, 'Output must be non-empty for an empty standings region');
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertSnapshotMatches($html, 'renderRegion-empty.html');
    }

    public function testStandingsRowViewNegativePath(): void
    {
        // All clinch flags = 0 (via tiedTeamA, which has all four flags unset).
        // getClinchTierClass must return '' and hasClinchStatus must return false.
        $noClinchedTeam = $this->tiedTeamA();

        $this->assertSame('', StandingsRowView::getClinchTierClass($noClinchedTeam), 'No clinch flags → empty CSS class');
        $this->assertFalse(StandingsRowView::hasClinchStatus($noClinchedTeam), 'No clinch flags → hasClinchStatus returns false');
    }

    public function testStandingsRowViewGetBottomLockedIndexesEmptyInput(): void
    {
        $rowView = new StandingsRowView();
        $this->assertSame([], $rowView->getBottomLockedIndexes([]), 'Empty standings → no bottom-locked indexes');
    }

    public function testTiebreakerResolverReturnsInputOrderOnEmptyMatrix(): void
    {
        $resolver = new \Standings\StandingsTiebreakerResolver();
        $teams = [$this->tiedTeamA(), $this->tiedTeamB()];
        $this->assertSame($teams, $resolver->resolveH2HTiedGroups($teams, []));
    }

    public function testTiebreakerResolverReturnsInputOrderOnNullMatrix(): void
    {
        $resolver = new \Standings\StandingsTiebreakerResolver();
        $teams = [$this->tiedTeamA(), $this->tiedTeamB()];
        $this->assertSame($teams, $resolver->resolveH2HTiedGroups($teams, null));
    }

    public function testTiebreakerResolverEmptyTeamListReturnsEmpty(): void
    {
        $resolver = new \Standings\StandingsTiebreakerResolver();
        $matrix = [1 => [2 => ['wins' => 2, 'losses' => 0]]];
        $this->assertSame([], $resolver->resolveH2HTiedGroups([], $matrix));
    }

    public function testTiebreakerResolverSingleTeamReturnsUnchanged(): void
    {
        $resolver = new \Standings\StandingsTiebreakerResolver();
        $matrix = [1 => [2 => ['wins' => 2, 'losses' => 0]]];
        $teams = [$this->tiedTeamA()];
        $this->assertSame($teams, $resolver->resolveH2HTiedGroups($teams, $matrix));
    }

    public function testTiebreakerResolverHandlesMissingMatrixEntry(): void
    {
        // Team 1 has no entry for team 2 in the matrix — must exercise the ?? 0 fallbacks.
        // Both aggregate pcts land at 0.0, PHP's stable sort preserves input order [1, 2].
        $resolver = new \Standings\StandingsTiebreakerResolver();
        $matrix = [1 => []];
        $teams = [$this->tiedTeamA(), $this->tiedTeamB()];
        $result = $resolver->resolveH2HTiedGroups($teams, $matrix);
        $teamOrder = array_map(static fn (array $t): int => $t['teamid'], $result);
        $this->assertSame([1, 2], $teamOrder, 'Missing matrix entry must fall back to 0 wins/losses and preserve input order');
    }
}
