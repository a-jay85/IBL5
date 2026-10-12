<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Boxscore\BoxscoreAuditRepository;

#[Group('database')]
class BoxscoreAuditRepositoryTest extends DatabaseTestCase
{
    private BoxscoreAuditRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BoxscoreAuditRepository($this->db);
    }

    // ── findDuplicateTripleGames ──────────────────────────────────

    /**
     * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, occurrences: int, gotds: string}> $rows
     */
    private static function hasTripleOn(array $rows, string $date): bool
    {
        foreach ($rows as $row) {
            if ($row['game_date'] === $date) {
                return true;
            }
        }

        return false;
    }

    public function testDuplicateInvariantIgnoresPlayoffDuplicatesWhenScopedToRegularSeason(): void
    {
        // Month 6 → game_type = 2 (playoffs), where a matchup legitimately repeats
        // on one date. Month 3 → game_type = 1, where it never should.
        $this->insertTeamBoxscoreRow('2097-06-05', 'Playoff1', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2097-06-05', 'Playoff2', 5, 2, 1);
        $this->insertTeamBoxscoreRow('2097-03-05', 'Regular1', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2097-03-05', 'Regular2', 5, 2, 1);

        $rows = $this->repo->findDuplicateTripleGames(2097, 1);

        self::assertTrue(self::hasTripleOn($rows, '2097-03-05'));
        self::assertFalse(self::hasTripleOn($rows, '2097-06-05'));
    }

    public function testFindDuplicateTripleGamesWithoutGameTypeKeepsExistingUnscopedBehavior(): void
    {
        // Same seed, no $gameType: the playoff pair is reported too — the pre-existing
        // behaviour both remaining single-argument callers still rely on.
        $this->insertTeamBoxscoreRow('2097-06-05', 'Playoff1', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2097-06-05', 'Playoff2', 5, 2, 1);

        $rows = $this->repo->findDuplicateTripleGames(2097);

        self::assertTrue(self::hasTripleOn($rows, '2097-06-05'));
    }

    public function testFindDuplicateTripleGamesNullSeasonYearFindsAllSeasonsIncludingNonCurrent(): void
    {
        // Two different seasons; a null $seasonYear drops the season predicate and
        // must surface both. Scoped to game_type = 1 so the assertion is about the
        // season axis alone.
        $this->insertTeamBoxscoreRow('2096-03-05', 'SeasonA1', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2096-03-05', 'SeasonA2', 5, 2, 1);
        $this->insertTeamBoxscoreRow('2097-03-05', 'SeasonB1', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2097-03-05', 'SeasonB2', 5, 2, 1);

        $rows = $this->repo->findDuplicateTripleGames(null, 1);

        self::assertTrue(self::hasTripleOn($rows, '2096-03-05'));
        self::assertTrue(self::hasTripleOn($rows, '2097-03-05'));
    }

    public function testFindScheduledGamesWithoutBoxscoresOmitsPlayedGameThatHasBoxscore(): void
    {
        // Season 2099 has no seed rows, so the whole list can be asserted exactly.
        $this->insertScheduleRow(2099, '2099-01-10', 1, 100, 2, 90);
        $this->insertTeamBoxscoreRow('2099-01-10', 'Pin Visitor', 1, 1, 2);
        // Control row so the result is not vacuously empty.
        $this->insertScheduleRow(2099, '2099-01-13', 1, 88, 2, 86);

        $rows = $this->repo->findScheduledGamesWithoutBoxscores(2099);

        self::assertSame(
            [['game_date' => '2099-01-13', 'visitor_teamid' => 1, 'home_teamid' => 2, 'visitor_score' => 88, 'home_score' => 86]],
            $rows
        );
    }

    public function testFindScheduledGamesWithoutBoxscoresListsPlayedGameMissingBoxscoreWithScores(): void
    {
        $this->insertScheduleRow(2099, '2099-01-11', 1, 101, 2, 97);

        $rows = $this->repo->findScheduledGamesWithoutBoxscores(2099);

        self::assertSame(
            [['game_date' => '2099-01-11', 'visitor_teamid' => 1, 'home_teamid' => 2, 'visitor_score' => 101, 'home_score' => 97]],
            $rows
        );
    }

    public function testFindScheduledGamesWithoutBoxscoresExcludesUnplayedZeroZeroGame(): void
    {
        // Unplayed 0-0 game must not be listed; the control row proves the query returns rows.
        $this->insertScheduleRow(2099, '2099-01-12', 1, 0, 2, 0);
        $this->insertScheduleRow(2099, '2099-01-13', 1, 88, 2, 86);

        $rows = $this->repo->findScheduledGamesWithoutBoxscores(2099);

        self::assertSame(
            [['game_date' => '2099-01-13', 'visitor_teamid' => 1, 'home_teamid' => 2, 'visitor_score' => 88, 'home_score' => 86]],
            $rows
        );
    }
}
