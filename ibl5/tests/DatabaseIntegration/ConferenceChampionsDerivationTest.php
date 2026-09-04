<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use RecordHolders\FranchiseRecordRepository;
use SeasonArchive\SeasonArchiveRepository;
use Team\TeamRepository;

/**
 * Integration tests for the conference-champions-from-playoffs derivation
 * introduced in migration 174 and the Phase 5 repository UNION branches.
 *
 * Each test seeds its own ibl_playoff_series_results and ibl_league_config rows
 * inside the inherited DatabaseTestCase transaction, which rolls back in tearDown().
 *
 * Synthetic year 2099 (and 2098 where a second season is needed) ensures no
 * real-data row in ibl_playoff_series_results or ibl_league_config can collide.
 *
 * IMPORTANT: This class does NOT replay migration 174. DDL in MariaDB implicitly
 * commits, which would destroy DatabaseTestCase's rollback() isolation for every
 * later test in the run. The vw_team_awards view is assumed live from bin/db-test-up.
 */
#[Group('database')]
final class ConferenceChampionsDerivationTest extends DatabaseTestCase
{
    private const YEAR  = 2099;
    private const YEAR2 = 2098;

    /** Monotonically-increasing team_slot within one test; reset by setUp(). */
    private int $nextSlot = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nextSlot = 1;
    }

    // ── Test methods ──────────────────────────────────────────────────────────

    /**
     * Happy path: two clinched round-3 series (one per conference) produce
     * exactly two conference champion rows in vw_team_awards.
     */
    public function testConferenceChampionsAreDerivedFromRoundThreeWinners(): void
    {
        $this->seedConferenceChampion(self::YEAR, 1, 'Metros', 2, 'Stars',   'Eastern');
        $this->seedConferenceChampion(self::YEAR, 3, 'Cougars', 4, 'Diesels', 'Western');

        $rows = $this->fetchConferenceAwardsForYear(self::YEAR);

        self::assertCount(2, $rows, 'Exactly two conference champion rows expected for year 2099');
        $byAward = array_column($rows, 'name', 'award');
        self::assertSame('Metros',  $byAward['Eastern Conference Champions'] ?? null);
        self::assertSame('Cougars', $byAward['Western Conference Champions'] ?? null);
    }

    /**
     * Negative path — the 2008 mid-playoffs case.
     * A round-3 series with only 3 wins recorded (below the 4-of-7 clinch
     * threshold of 4) must produce no conference award.
     */
    public function testInProgressConferenceFinalsYieldNoAward(): void
    {
        // winner_games = 3 < 4 (clinch threshold from '4 of 7') → no award
        $this->seedConferenceChampion(self::YEAR, 1, 'Metros', 2, 'Stars', 'Eastern', '4 of 7', 3);

        $rows = $this->fetchConferenceAwardsForYear(self::YEAR);

        self::assertCount(0, $rows, 'In-progress series (3 wins, threshold 4) must not produce a conference award');
    }

    /**
     * Fail-closed guard: an unparseable playoff_round3_format value suppresses
     * the award rather than trivially awarding it.
     *
     * CAST('INVALID' AS UNSIGNED) = 0 in MariaDB.
     * NULLIF(0, 0) = NULL.
     * winner_games >= NULL evaluates to NULL (not TRUE).
     * The row is filtered, and count = 0.
     *
     * A bare CAST without NULLIF would return 0 and make winner_games >= 0
     * trivially true — awarding a title mid-series. This test pins that the
     * NULLIF guard shipped and is not "simplified" away.
     *
     * Note: playoff_round3_format is VARCHAR(8); 'INVALID' (7 chars) fits and
     * is unparseable — the column carries the real-world format values like
     * '4 of 7', not arbitrary English prose.
     */
    public function testMalformedRoundThreeFormatSuppressesAward(): void
    {
        $this->seedConferenceChampion(self::YEAR, 1, 'Metros', 2, 'Stars', 'Eastern', 'INVALID', 4);

        $rows = $this->fetchConferenceAwardsForYear(self::YEAR);

        self::assertCount(0, $rows, 'Unparseable playoff_round3_format must suppress the award (fail-closed via NULLIF)');
    }

    /**
     * Renamed-franchise bridge: a winner whose psr.winner string differs from
     * the ibl_league_config.team_name entry for that season is resolved through
     * ibl_franchise_seasons, which carries the historical name.
     *
     * Fixture shape:
     *   psr.winner      = 'Metros'     (current ibl_team_info.team_name)
     *   fs.team_name    = 'Old Metros' (historical name for tid=1 in year 2099)
     *   lc.team_name    = 'Old Metros' (config keyed on the historical name)
     *
     * COALESCE(fs.team_name, psr.winner) = 'Old Metros' → matches lc.
     * Without the fs row: COALESCE(NULL, 'Metros') = 'Metros' → no lc match →
     * award vanishes (the exact failure that made a naïve join return 38 of 40).
     */
    public function testRenamedFranchiseResolvesConferenceViaFranchiseSeasons(): void
    {
        $this->insertPlayoffSeriesResultRow(self::YEAR, 3, 1, 2, 'Metros', 'Stars', 4, 2);
        $this->insertFranchiseSeasonRow(1, self::YEAR, 'Old Metros');
        $this->insertRow('ibl_league_config', [
            'season_ending_year'          => self::YEAR,
            'team_slot'                   => $this->nextSlot++,
            'team_name'                   => 'Old Metros',
            'conference'                  => 'Eastern',
            'division'                    => 'Atlantic',
            'playoff_qualifiers_per_conf' => 4,
            'playoff_round1_format'       => '4 of 7',
            'playoff_round2_format'       => '4 of 7',
            'playoff_round3_format'       => '4 of 7',
            'playoff_round4_format'       => '4 of 7',
            'team_count'                  => 20,
        ]);

        $rows = $this->fetchConferenceAwardsForYear(self::YEAR);

        self::assertCount(1, $rows, 'Renamed-franchise bridge must resolve the award');
        self::assertSame('Eastern Conference Champions', $rows[0]['award']);
        // name comes from psr.winner, not the historical alias
        self::assertSame('Metros', $rows[0]['name']);
    }

    /**
     * The round = 3 literal is not generalized to MAX(round).
     * Adding a round-4 (Finals) row on top of the two round-3 rows must leave
     * exactly two conference rows — not three — proving the literal pins it.
     */
    public function testRoundFourWinnerDoesNotProduceExtraConferenceTitle(): void
    {
        $this->seedConferenceChampion(self::YEAR, 1, 'Metros',  2, 'Stars',   'Eastern');
        $this->seedConferenceChampion(self::YEAR, 3, 'Cougars', 4, 'Diesels', 'Western');
        // Round 4 (Finals): Metros beats Cougars — must be filtered by psr.round = 3
        $this->insertPlayoffSeriesResultRow(self::YEAR, 4, 1, 3, 'Metros', 'Cougars', 4, 2);

        $rows = $this->fetchConferenceAwardsForYear(self::YEAR);

        self::assertCount(2, $rows, 'Round 4 (Finals) row must not produce an extra conference title');
    }

    /**
     * Reader-layer test for the Phase 5 FranchiseRecordRepository UNION branch.
     *
     * Without that UNION branch getMostTitlesByType('Conference') returns an
     * empty list for the test DB (no stored conference rows after migration 174
     * deleted them). With it, the derived 2099 titles flow through and both
     * synthetic teams appear with count = 1.
     */
    public function testMostConferenceTitlesLeaderboardIncludesDerivedTitles(): void
    {
        $this->seedConferenceChampion(self::YEAR, 1, 'Metros',  2, 'Stars',   'Eastern');
        $this->seedConferenceChampion(self::YEAR, 3, 'Cougars', 4, 'Diesels', 'Western');

        $repo    = new FranchiseRecordRepository($this->db);
        $records = $repo->getMostTitlesByType('Conference');

        self::assertNotEmpty($records, 'Leaderboard must be non-empty — Phase 5 UNION branch is absent if this fails');
        $byName = array_column($records, null, 'team_name');
        self::assertArrayHasKey('Metros',  $byName, 'Metros must appear in the leaderboard');
        self::assertArrayHasKey('Cougars', $byName, 'Cougars must appear in the leaderboard');
        self::assertSame(1, $byName['Metros']['count'],  'Metros count must be 1');
        self::assertSame(1, $byName['Cougars']['count'], 'Cougars count must be 1');
    }

    /**
     * Bind-order lock for the Phase 5 SeasonArchiveRepository conference branch.
     *
     * getTeamAwardsByYear(int $year, int $heatYear) binds four parameters:
     *   1. $year     → ibl_team_awards WHERE year = ?
     *   2. $year     → Champions branch WHERE psr.year = ?
     *   3. $year     → Conference branch WHERE psr.year = ?  ← the new bind
     *   4. $heatYear → HEAT branch WHERE YEAR(game_date) = ?
     *
     * If bind 3 and 4 are swapped (the defect), the conference branch receives
     * $heatYear = 2098 and returns 2098 winners (Cougars, Diesels) instead of
     * 2099 winners (Metros, Stars). The defect is silent: query compiles, binds,
     * and returns a full plausible award list for the wrong season.
     */
    public function testSeasonArchiveAwardsUseTheArchiveYearNotTheHeatYear(): void
    {
        // 2099: East = Metros, West = Stars
        $this->seedConferenceChampion(self::YEAR,  1, 'Metros',  2, 'Stars',   'Eastern');
        $this->seedConferenceChampion(self::YEAR,  2, 'Stars',   1, 'Metros',  'Western');
        // 2098: East = Cougars, West = Diesels
        $this->seedConferenceChampion(self::YEAR2, 3, 'Cougars', 4, 'Diesels', 'Eastern');
        $this->seedConferenceChampion(self::YEAR2, 4, 'Diesels', 3, 'Cougars', 'Western');

        $repo   = new SeasonArchiveRepository($this->db);
        $awards = $repo->getTeamAwardsByYear(self::YEAR, self::YEAR2);

        $conferenceAwards = array_values(array_filter(
            $awards,
            static fn (array $row): bool => str_contains((string) $row['award'], 'Conference Champions')
        ));

        $names = array_column($conferenceAwards, 'name');
        self::assertContains('Metros',  $names, '2099 East winner Metros must appear');
        self::assertContains('Stars',   $names, '2099 West winner Stars must appear');
        self::assertNotContains('Cougars', $names, '2098 East winner Cougars must not appear when querying year 2099');
        self::assertNotContains('Diesels', $names, '2098 West winner Diesels must not appear when querying year 2099');
    }

    /**
     * Predicate lock for the Phase 5 TeamRepository conference branch.
     *
     * The conference sub-select adds `AND ranked.name = ?` so only the queried
     * team's own titles flow through. Without that predicate, every franchise's
     * conference titles appear on every team page — the defect compiles, binds,
     * and returns a full plausible list with no error.
     */
    public function testTeamAccomplishmentsAreScopedToTheQueriedTeam(): void
    {
        $this->seedConferenceChampion(self::YEAR, 1, 'Metros', 2, 'Stars',  'Eastern');
        $this->seedConferenceChampion(self::YEAR, 2, 'Stars',  1, 'Metros', 'Western');

        $repo            = new TeamRepository($this->db);
        $accomplishments = $repo->getTeamAccomplishments('Metros');

        $awards = array_column($accomplishments, 'award');
        self::assertContains(
            'Eastern Conference Champions',
            $awards,
            'Metros Eastern title must appear in Metros accomplishments'
        );
        self::assertNotContains(
            'Western Conference Champions',
            $awards,
            "Stars' Western title must not bleed onto Metros' accomplishments page"
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Seed one conference finals series and the matching ibl_league_config row.
     *
     * ibl_league_config has a UNIQUE KEY on (season_ending_year, team_slot).
     * $this->nextSlot auto-increments so multiple calls within one test are safe.
     *
     * @param string $round3Format Parseable format string, e.g. '4 of 7'. Use an
     *                             unparseable string like 'best-of-seven' to test
     *                             the NULLIF fail-closed guard.
     * @param int    $winnerGames  Games won by the winner. Values below the clinch
     *                             threshold (first token of $round3Format) model an
     *                             in-progress series.
     */
    private function seedConferenceChampion(
        int $year,
        int $winnerTid,
        string $winnerName,
        int $loserTid,
        string $loserName,
        string $conference,
        string $round3Format = '4 of 7',
        int $winnerGames = 4,
    ): void {
        $this->insertRow('ibl_league_config', [
            'season_ending_year'          => $year,
            'team_slot'                   => $this->nextSlot++,
            'team_name'                   => $winnerName,
            'conference'                  => $conference,
            'division'                    => $conference === 'Eastern' ? 'Atlantic' : 'Pacific',
            'playoff_qualifiers_per_conf' => 4,
            'playoff_round1_format'       => '4 of 7',
            'playoff_round2_format'       => '4 of 7',
            'playoff_round3_format'       => $round3Format,
            'playoff_round4_format'       => '4 of 7',
            'team_count'                  => 20,
        ]);

        $loserGames = $winnerGames >= 4 ? 3 : max(0, $winnerGames - 1);
        $this->insertPlayoffSeriesResultRow(
            $year,
            3,
            $winnerTid,
            $loserTid,
            $winnerName,
            $loserName,
            $winnerGames,
            $loserGames,
        );
    }

    /**
     * Query vw_team_awards for conference champion rows in a given year.
     *
     * @return list<array{year: int, name: string, award: string}>
     */
    private function fetchConferenceAwardsForYear(int $year): array
    {
        $stmt = $this->db->prepare(
            "SELECT year, name, award
             FROM vw_team_awards
             WHERE award IN ('Eastern Conference Champions', 'Western Conference Champions')
               AND year = ?
             ORDER BY award ASC"
        );
        self::assertNotFalse($stmt, 'Failed to prepare conference awards query: ' . $this->db->error);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var list<array{year: int, name: string, award: string}> $rows */
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            /** @var array{year: int, name: string, award: string} $row */
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}
