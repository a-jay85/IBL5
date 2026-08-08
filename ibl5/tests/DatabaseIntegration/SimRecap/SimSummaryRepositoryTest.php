<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
final class SimSummaryRepositoryTest extends DatabaseTestCase
{
    private SimSummaryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SimSummaryRepository($this->db);
    }

    // ── Happy-path tests ───────────────────────────────────────────────────────

    public function testQueuePendingIfAbsentCreatesARow(): void
    {
        $created = $this->repo->queuePendingIfAbsent(999001);
        self::assertTrue($created);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, $row['attempts']);
    }

    public function testClaimNextPendingClaimsTheOldestFirst(): void
    {
        $this->repo->queuePendingIfAbsent(999002);
        $this->repo->queuePendingIfAbsent(999001);

        $claimed = $this->repo->claimNextPending();
        self::assertSame(999001, $claimed);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('generating', $row['status']);
        self::assertSame(1, $row['attempts']);
        self::assertNotNull($row['claimed_at']);
    }

    public function testMarkDoneStoresTextAndThemes(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        $gameRecap = $this->gameRecap(sortOrder: 0, gameOfThatDay: 1);
        $this->repo->markDone(999001, 'Intro.', 'Outro.', 'Recap prose.', [$gameRecap], '["comeback"]');

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('done', $row['status']);
        self::assertSame('Recap prose.', $row['recap_text']);
        self::assertSame('Intro.', $row['intro_text']);
        self::assertSame('Outro.', $row['outro_text']);
        self::assertSame('["comeback"]', $row['themes_used']);
        self::assertNotNull($row['generated_at']);

        $recaps = $this->repo->findGameRecaps(999001);
        self::assertCount(1, $recaps);
        self::assertSame(2025, $recaps[0]['season_year']);
        self::assertSame('2025-01-01', $recaps[0]['game_date']);
        self::assertSame(0, $recaps[0]['sort_order']);
    }

    public function testMarkDoneUpsertsWhenNoRowWasQueued(): void
    {
        $this->repo->markDone(999005, 'Intro.', 'Outro.', 'Upserted recap.', [], null);

        $row = $this->repo->find(999005);
        self::assertNotNull($row);
        self::assertSame('done', $row['status']);
    }

    public function testRecentThemesReturnsNewestFirstAndCapsAtTheLimit(): void
    {
        // Insert six done rows for sims 999010–999015 with distinct themes
        for ($sim = 999010; $sim <= 999015; $sim++) {
            $this->repo->markDone($sim, 'Intro.', 'Outro.', "Recap for sim {$sim}.", [], "[\"theme-{$sim}\"]");
        }

        $rows = $this->repo->recentThemes(5);

        self::assertCount(5, $rows, 'recentThemes(5) must return exactly 5 rows');
        // Newest first: 999015, 999014, 999013, 999012, 999011
        self::assertSame('["theme-999015"]', $rows[0]['themes_used'], 'First row must be newest sim');
        self::assertSame('["theme-999011"]', $rows[4]['themes_used'], 'Last row must be fifth-newest sim');
    }

    // ── Negative / boundary tests ──────────────────────────────────────────────

    public function testQueuePendingIfAbsentIsIdempotent(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        $created = $this->repo->queuePendingIfAbsent(999001);
        self::assertFalse($created);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('generating', $row['status'], 'Status must not be reset to pending');
    }

    public function testClaimPendingLosesTheRace(): void
    {
        $this->repo->queuePendingIfAbsent(999001);

        $first = $this->repo->claimPending(999001);
        self::assertTrue($first);

        $second = $this->repo->claimPending(999001);
        self::assertFalse($second);
    }

    public function testClaimNextPendingReturnsNullWhenQueueIsEmpty(): void
    {
        self::assertNull($this->repo->claimNextPending());
    }

    public function testClaimNextPendingSkipsABlockedRow(): void
    {
        // Part 1: 999001 blocked, 999002 unblocked — expect 999002
        $this->repo->queuePendingIfAbsent(999001);
        $this->db->query(
            "UPDATE `ibl_sim_summaries` SET `blocked_until` = NOW() + INTERVAL 60 MINUTE WHERE `sim` = 999001"
        );
        $this->repo->queuePendingIfAbsent(999002);

        $claimed = $this->repo->claimNextPending();
        self::assertSame(999002, $claimed);

        // Part 2 (boundary): blocked_until = NOW() should be eligible (<=)
        // 999001 is still blocked 60 minutes out, 999002 is now generating.
        $this->repo->queuePendingIfAbsent(999003);
        $this->db->query(
            "UPDATE `ibl_sim_summaries` SET `blocked_until` = NOW() WHERE `sim` = 999003"
        );

        $boundary = $this->repo->claimNextPending();
        self::assertSame(999003, $boundary, 'blocked_until = NOW() must be eligible for claiming');
    }

    public function testParkOrFailParksBelowTheCeiling(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        $result = $this->repo->parkOrFail(999001);
        self::assertSame('pending', $result);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);
        self::assertNull($row['claimed_at']);
        self::assertNotNull($row['blocked_until'], 'blocked_until must be set after parking');
    }

    public function testParkOrFailFailsAtTheCeiling(): void
    {
        // Queue and claim once (attempts=1)
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        // Reset to pending manually so we can claim again
        $this->db->query(
            "UPDATE `ibl_sim_summaries` SET `status` = 'pending', `claimed_at` = NULL WHERE `sim` = 999001"
        );

        // Claim again (attempts=2, at ceiling)
        $this->repo->claimPending(999001);

        $result = $this->repo->parkOrFail(999001);
        self::assertSame('failed', $result, 'At attempts >= 2 ceiling, parkOrFail must fail the row');

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('failed', $row['status']);
        self::assertNull($row['blocked_until']);
    }

    public function testParkOrFailReturnsNoneWhenNotGenerating(): void
    {
        $this->repo->markDone(999001, 'i', 'o', 'text', [], null);

        $result = $this->repo->parkOrFail(999001);
        self::assertSame('none', $result);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('done', $row['status'], 'parkOrFail(none) must not touch a done row');
    }

    public function testReclaimStaleClaimIgnoresAFreshClaim(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        $reclaimed = $this->repo->reclaimStaleClaim(45);
        self::assertNull($reclaimed, 'A freshly-claimed row must not be reclaimed');
    }

    public function testReclaimStaleClaimRecoversAnAbandonedRow(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        // Back-date claimed_at to simulate abandonment
        $this->db->query(
            "UPDATE `ibl_sim_summaries` SET `claimed_at` = NOW() - INTERVAL 46 MINUTE WHERE `sim` = 999001"
        );

        $reclaimed = $this->repo->reclaimStaleClaim(45);
        self::assertSame(999001, $reclaimed);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);
        self::assertNull($row['claimed_at']);

        // Second call must return null — no double reclaim
        $second = $this->repo->reclaimStaleClaim(45);
        self::assertNull($second, 'Re-asserted predicates must prevent double reclaim');
    }

    public function testRecentThemesToleratesMalformedJson(): void
    {
        $this->repo->markDone(999001, 'i', 'o', 'text', [], '"not-a-list"');

        $rows = $this->repo->recentThemes(5);

        self::assertNotEmpty($rows, 'recentThemes must return rows even with malformed JSON');
        $found = false;
        foreach ($rows as $row) {
            if ($row['themes_used'] === '"not-a-list"') {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Malformed JSON must be returned as-is without throwing');
    }

    // ── listAll() — the admin viewer index ─────────────────────────────────────

    public function testListAllReturnsEveryRowNewestSimFirst(): void
    {
        $this->clearSummaries();
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->queuePendingIfAbsent(999003);
        $this->repo->markDone(999003, 'Intro three.', 'Outro three.', 'Body three.', [], null);
        $this->repo->queuePendingIfAbsent(999002);
        $this->db->query("UPDATE `ibl_sim_summaries` SET `status` = 'failed' WHERE `sim` = 999002");

        $rows = $this->repo->listAll();

        self::assertCount(3, $rows);
        self::assertSame([999003, 999002, 999001], array_map(
            static fn (array $row): int => (int) $row['sim'],
            $rows
        ));
    }

    public function testListAllExposesRecapLengthWithoutTheBody(): void
    {
        $this->clearSummaries();
        $this->repo->queuePendingIfAbsent(999003);
        $this->repo->markDone(999003, 'Intro three.', 'Outro three.', 'Body three.', [], null);

        $rows = $this->repo->listAll();

        self::assertCount(1, $rows);
        self::assertSame(11, (int) $rows[0]['recap_length']);
        self::assertArrayNotHasKey('recap_text', $rows[0], 'The index must never load MEDIUMTEXT bodies');
    }

    public function testListAllReturnsAnEmptyArrayWhenNoRowsExist(): void
    {
        $this->clearSummaries();

        self::assertSame([], $this->repo->listAll());
    }

    // ── findGameRecaps tests ───────────────────────────────────────────────────

    public function testMarkDoneStoresGameRecapsInSortOrder(): void
    {
        // Insert sort_order=1 first, sort_order=0 second — findGameRecaps must return 0,1
        $games = [
            $this->gameRecap(sortOrder: 1, gameOfThatDay: 1),
            $this->gameRecap(sortOrder: 0, gameOfThatDay: 2),
        ];
        $this->repo->markDone(999001, 'Intro.', 'Outro.', 'Recap.', $games, null);

        $recaps = $this->repo->findGameRecaps(999001);

        self::assertCount(2, $recaps);
        self::assertSame(0, $recaps[0]['sort_order'], 'First row must have sort_order=0');
        self::assertSame(1, $recaps[1]['sort_order'], 'Second row must have sort_order=1');
        // Natural-key and prose round-trip
        self::assertSame(2025, $recaps[0]['season_year']);
        self::assertSame('2025-01-01', $recaps[0]['game_date']);
        self::assertSame('Recap for game 2.', $recaps[0]['recap_text']);
        self::assertSame('Recap for game 1.', $recaps[1]['recap_text']);
    }

    public function testMarkDoneWithNullBoxIdRoundTripsAsNull(): void
    {
        $game = $this->gameRecap(sortOrder: 0, gameOfThatDay: 1, boxId: null);
        $this->repo->markDone(999001, 'Intro.', 'Outro.', 'Recap.', [$game], null);

        $recaps = $this->repo->findGameRecaps(999001);

        self::assertCount(1, $recaps);
        self::assertNull($recaps[0]['box_id'], 'A null box_id must round-trip as null');
    }

    public function testMarkDoneReStoreIsIdempotent(): void
    {
        $game = $this->gameRecap(sortOrder: 0, gameOfThatDay: 1);
        $this->repo->markDone(999001, 'First intro.', 'First outro.', 'First recap.', [$game], null);

        // Second call with updated prose — ODKU must update in place, not duplicate
        $this->repo->markDone(999001, 'Second intro.', 'Second outro.', 'Second recap.', [$game], null);

        $recaps = $this->repo->findGameRecaps(999001);
        self::assertCount(1, $recaps, 'Re-store must not create duplicate game rows');

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('Second recap.', $row['recap_text'], 'recap_text must reflect the second call');
    }

    public function testMarkDoneRollsBackEnvelopeWhenAChildFails(): void
    {
        // 'not-a-date' is an invalid DATE value; MariaDB strict mode rejects it
        // mid-transaction, causing the SAVEPOINT to roll back the envelope too.
        $badGame = [
            'season_year'      => 2025,
            'game_date'        => 'not-a-date',
            'visitor_teamid'   => 1,
            'home_teamid'      => 2,
            'game_of_that_day' => 1,
            'box_id'           => null,
            'sort_order'       => 0,
            'recap_text'       => 'A recap.',
        ];

        $threw = false;
        try {
            $this->repo->markDone(999099, 'Intro.', 'Outro.', 'Recap.', [$badGame], null);
        } catch (\Throwable) {
            $threw = true;
        }

        self::assertTrue($threw, 'markDone must throw when a child game insert is rejected by the DB');
        self::assertNull(
            $this->repo->find(999099),
            'Envelope row must not persist after the transaction rolls back'
        );
        self::assertSame(
            [],
            $this->repo->findGameRecaps(999099),
            'Game recap rows must not persist after the transaction rolls back'
        );
    }

    // ── findDisplayableGameRecaps tests ──────────────────────────────────────────

    public function testFindDisplayableGameRecapsReturnsOnlyGamesWithBoxScores(): void
    {
        // Two game recaps: gotd=1 (will have a matching box score) and gotd=2 (will not).
        $games = [
            $this->gameRecap(sortOrder: 0, gameOfThatDay: 1),
            $this->gameRecap(sortOrder: 1, gameOfThatDay: 2),
        ];
        $this->repo->markDone(999001, 'Intro.', 'Outro.', 'Recap.', $games, null);

        // Two team-side rows for gotd=1 only (unique key includes `name`, so both persist).
        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-01-01', 1, 2, 1, 'Metros'), ('2025-01-01', 1, 2, 1, 'Stars')"
        );

        $displayable = $this->repo->findDisplayableGameRecaps(999001);

        self::assertCount(1, $displayable, 'Only games with a matching box score are returned');
        self::assertSame(1, $displayable[0]['game_of_that_day'], 'The returned game is the one backed by a box score');
    }

    public function testFindDisplayableGameRecapsReturnsEmptyArrayWhenNoBoxScoresExist(): void
    {
        // Store one game recap with no corresponding ibl_box_scores_teams row.
        $this->repo->markDone(999001, 'Intro.', 'Outro.', 'Recap.', [$this->gameRecap(sortOrder: 0)], null);

        $displayable = $this->repo->findDisplayableGameRecaps(999001);

        self::assertSame([], $displayable, 'No box score match → no displayable game recaps');
    }

    public function testFindDisplayableGameRecapsSingleGameDaySurvives(): void
    {
        $game = $this->gameRecap(sortOrder: 0, gameOfThatDay: 1);
        $this->repo->markDone(999090, 'Intro.', 'Outro.', 'Recap.', [$game], null);

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-01-01', 1, 2, 1, 'Metros')"
        );

        $displayable = $this->repo->findDisplayableGameRecaps(999090);

        self::assertCount(1, $displayable, 'Single game with gotd=1 must be returned');
        self::assertSame(1, $displayable[0]['game_of_that_day']);
    }

    /**
     * Two rows sharing a date and matchup but carrying different indices both match,
     * because the `EXISTS` filter compares the index, not the matchup.
     */
    public function testFindDisplayableGameRecapsTwoIndicesOnOneDateBothReturned(): void
    {
        $games = [
            $this->gameRecap(sortOrder: 0, gameOfThatDay: 1),
            $this->gameRecap(sortOrder: 1, gameOfThatDay: 2),
        ];
        $this->repo->markDone(999091, 'Intro.', 'Outro.', 'Recap.', $games, null);

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-01-01', 1, 2, 1, 'Metros'), ('2025-01-01', 1, 2, 2, 'Stars')"
        );

        $displayable = $this->repo->findDisplayableGameRecaps(999091);

        self::assertCount(2, $displayable, 'Both indices on one date must be returned');
        self::assertSame(1, $displayable[0]['game_of_that_day']);
        self::assertSame(2, $displayable[1]['game_of_that_day']);
    }

    public function testFindDisplayableGameRecapsMismatchDropped(): void
    {
        // Since payload validation now rejects game_of_that_day < 1 (B2), this scenario
        // cannot arise via SimRecapPayload::fromJson(). This test pins the DB-level behavior:
        // a recap stored with gotd=0 is correctly dropped by the display query.
        $game = $this->gameRecap(sortOrder: 0, gameOfThatDay: 0);
        $this->repo->markDone(999092, 'Intro.', 'Outro.', 'Recap.', [$game], null);

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-01-01', 1, 2, 1, 'Metros')"
        );

        $displayable = $this->repo->findDisplayableGameRecaps(999092);

        self::assertSame([], $displayable, 'gotd=0 recap must not match a gotd=1 box score');
    }

    /**
     * Seven distinct matchups on 2025-02-14, each carrying its true index (1–7).
     * Pins the real semantics of game_of_that_day: one entry per league game on a
     * given date, not a per-matchup doubleheader counter. All seven must be
     * displayable when each index matches a box score row.
     */
    public function testFindDisplayableGameRecapsSevenGameNightAllSurvive(): void
    {
        $games = [
            $this->leagueGame(0, 1,  2,  1),
            $this->leagueGame(1, 3,  4,  2),
            $this->leagueGame(2, 5,  6,  3),
            $this->leagueGame(3, 7,  8,  4),
            $this->leagueGame(4, 9,  10, 5),
            $this->leagueGame(5, 11, 12, 6),
            $this->leagueGame(6, 13, 14, 7),
        ];
        $this->repo->markDone(999093, 'Intro.', 'Outro.', 'Recap.', $games, null);

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-02-14', 1, 2, 1, 'T1'), ('2025-02-14', 3, 4, 2, 'T2'), ('2025-02-14', 5, 6, 3, 'T3')," .
            "        ('2025-02-14', 7, 8, 4, 'T4'), ('2025-02-14', 9, 10, 5, 'T5'), ('2025-02-14', 11, 12, 6, 'T6')," .
            "        ('2025-02-14', 13, 14, 7, 'T7')"
        );

        $displayable = $this->repo->findDisplayableGameRecaps(999093);

        self::assertCount(7, $displayable);
        self::assertSame([1, 2, 3, 4, 5, 6, 7], array_column($displayable, 'game_of_that_day'));
    }

    /**
     * Sim-725 shape. Seven distinct matchups store game_of_that_day = 1 for every
     * game. The box scores carry the real indices 1–7, one per matchup. Only the
     * game whose stored index happens to equal the box-score index (matchup 1/2 with
     * game_of_that_day = 1) survives the EXISTS filter; the other six are silently
     * dropped. Characterizes the display-collapse defect — fixed by the write-time
     * guard in Phase 4.
     */
    public function testFindDisplayableGameRecapsAllOnesCollapseToASingleGame(): void
    {
        $games = [
            $this->leagueGame(0, 1,  2,  1),
            $this->leagueGame(1, 3,  4,  1),
            $this->leagueGame(2, 5,  6,  1),
            $this->leagueGame(3, 7,  8,  1),
            $this->leagueGame(4, 9,  10, 1),
            $this->leagueGame(5, 11, 12, 1),
            $this->leagueGame(6, 13, 14, 1),
        ];
        $this->repo->markDone(999094, 'Intro.', 'Outro.', 'Recap.', $games, null);

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-02-14', 1, 2, 1, 'T1'), ('2025-02-14', 3, 4, 2, 'T2'), ('2025-02-14', 5, 6, 3, 'T3')," .
            "        ('2025-02-14', 7, 8, 4, 'T4'), ('2025-02-14', 9, 10, 5, 'T5'), ('2025-02-14', 11, 12, 6, 'T6')," .
            "        ('2025-02-14', 13, 14, 7, 'T7')"
        );

        $displayable = $this->repo->findDisplayableGameRecaps(999094);

        self::assertCount(1, $displayable);
        self::assertSame([1, 2], [$displayable[0]['visitor_teamid'], $displayable[0]['home_teamid']]);
    }

    // ── validateGameRowsJoinToBoxScores tests ─────────────────────────────────

    public function testValidateGameRowsJoinToBoxScoresAcceptsASevenGameNight(): void
    {
        $this->expectNotToPerformAssertions();

        $games = [
            $this->leagueGame(0, 1,  2,  1),
            $this->leagueGame(1, 3,  4,  2),
            $this->leagueGame(2, 5,  6,  3),
            $this->leagueGame(3, 7,  8,  4),
            $this->leagueGame(4, 9,  10, 5),
            $this->leagueGame(5, 11, 12, 6),
            $this->leagueGame(6, 13, 14, 7),
        ];

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-02-14', 1, 2, 1, 'T1'), ('2025-02-14', 3, 4, 2, 'T2'), ('2025-02-14', 5, 6, 3, 'T3')," .
            "        ('2025-02-14', 7, 8, 4, 'T4'), ('2025-02-14', 9, 10, 5, 'T5'), ('2025-02-14', 11, 12, 6, 'T6')," .
            "        ('2025-02-14', 13, 14, 7, 'T7')"
        );

        $this->repo->validateGameRowsJoinToBoxScores($games);
    }

    public function testValidateGameRowsJoinToBoxScoresRejectsTheSim725Shape(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/6 of 7 game rows do not match an archived box score/');

        $games = [
            $this->leagueGame(0, 1,  2,  1),
            $this->leagueGame(1, 3,  4,  1),
            $this->leagueGame(2, 5,  6,  1),
            $this->leagueGame(3, 7,  8,  1),
            $this->leagueGame(4, 9,  10, 1),
            $this->leagueGame(5, 11, 12, 1),
            $this->leagueGame(6, 13, 14, 1),
        ];

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-02-14', 1, 2, 1, 'T1'), ('2025-02-14', 3, 4, 2, 'T2'), ('2025-02-14', 5, 6, 3, 'T3')," .
            "        ('2025-02-14', 7, 8, 4, 'T4'), ('2025-02-14', 9, 10, 5, 'T5'), ('2025-02-14', 11, 12, 6, 'T6')," .
            "        ('2025-02-14', 13, 14, 7, 'T7')"
        );

        $this->repo->validateGameRowsJoinToBoxScores($games);
    }

    public function testValidateGameRowsJoinToBoxScoresRejectsAGameWithNoArchivedBoxScore(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/1 of 1 game rows/');

        $games = [$this->leagueGame(0, 1, 2, 1)];

        $this->repo->validateGameRowsJoinToBoxScores($games);
    }

    public function testValidateGameRowsJoinToBoxScoresTreatsANullBoxScoreIndexAsZero(): void
    {
        $this->expectNotToPerformAssertions();

        $games = [$this->leagueGame(0, 1, 2, 0)];

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-02-14', 1, 2, NULL, 'T1')"
        );

        $this->repo->validateGameRowsJoinToBoxScores($games);
    }

    public function testValidateGameRowsJoinToBoxScoresAcceptsAnEmptyGameList(): void
    {
        $this->expectNotToPerformAssertions();

        $this->repo->validateGameRowsJoinToBoxScores([]);
    }

    public function testValidatedGameRowsAreAlwaysDisplayable(): void
    {
        $games = [
            $this->leagueGame(0, 1,  2,  1),
            $this->leagueGame(1, 3,  4,  2),
            $this->leagueGame(2, 5,  6,  3),
            $this->leagueGame(3, 7,  8,  4),
            $this->leagueGame(4, 9,  10, 5),
            $this->leagueGame(5, 11, 12, 6),
            $this->leagueGame(6, 13, 14, 7),
        ];

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-02-14', 1, 2, 1, 'T1'), ('2025-02-14', 3, 4, 2, 'T2'), ('2025-02-14', 5, 6, 3, 'T3')," .
            "        ('2025-02-14', 7, 8, 4, 'T4'), ('2025-02-14', 9, 10, 5, 'T5'), ('2025-02-14', 11, 12, 6, 'T6')," .
            "        ('2025-02-14', 13, 14, 7, 'T7')"
        );

        $this->repo->validateGameRowsJoinToBoxScores($games);
        $this->repo->markDone(999095, 'Intro.', 'Outro.', 'Recap.', $games, null);

        $displayable = $this->repo->findDisplayableGameRecaps(999095);

        self::assertCount(7, $displayable, 'All validated game rows must be displayable');
    }

    public function testValidateGameRowsJoinToBoxScoresDoesNotLeakRecapProseIntoTheFailure(): void
    {
        $games = [
            $this->leagueGame(0, 1,  2,  1),
            $this->leagueGame(1, 3,  4,  1),
            $this->leagueGame(2, 5,  6,  1),
            $this->leagueGame(3, 7,  8,  1),
            $this->leagueGame(4, 9,  10, 1),
            $this->leagueGame(5, 11, 12, 1),
            $this->leagueGame(6, 13, 14, 1),
        ];

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-02-14', 1, 2, 1, 'T1'), ('2025-02-14', 3, 4, 2, 'T2'), ('2025-02-14', 5, 6, 3, 'T3')," .
            "        ('2025-02-14', 7, 8, 4, 'T4'), ('2025-02-14', 9, 10, 5, 'T5'), ('2025-02-14', 11, 12, 6, 'T6')," .
            "        ('2025-02-14', 13, 14, 7, 'T7')"
        );

        try {
            $this->repo->validateGameRowsJoinToBoxScores($games);
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringNotContainsString('Recap for game', $e->getMessage());
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Migration 155 seeds one `done` row (MAX(ibl_sim_dates.sim)) into every
     * migrated database, so any test asserting an exact row set must clear the
     * table first. DELETE (not TRUNCATE) — TRUNCATE auto-commits in MySQL and
     * would break DatabaseTestCase's per-test transaction rollback.
     */
    private function clearSummaries(): void
    {
        $this->db->query('DELETE FROM `ibl_sim_summaries`');
    }

    /**
     * Build a minimal valid game recap array for markDone's $gameRecaps parameter.
     * Vary $gameOfThatDay to produce distinct natural keys (avoiding UNIQUE violations).
     *
     * @return array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}
     */
    private function gameRecap(int $sortOrder, int $gameOfThatDay = 1, ?int $boxId = 42): array
    {
        return [
            'season_year'      => 2025,
            'game_date'        => '2025-01-01',
            'visitor_teamid'   => 1,
            'home_teamid'      => 2,
            'game_of_that_day' => $gameOfThatDay,
            'box_id'           => $boxId,
            'sort_order'       => $sortOrder,
            'recap_text'       => "Recap for game {$gameOfThatDay}.",
        ];
    }

    /**
     * A game recap for a distinct matchup, so seven of them share a date without
     * colliding on uniq_game (season_year, game_date, visitor, home, game_of_that_day).
     *
     * @return array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}
     */
    private function leagueGame(int $sortOrder, int $visitor, int $home, int $gameOfThatDay): array
    {
        return [
            'season_year'      => 2025,
            'game_date'        => '2025-02-14',
            'visitor_teamid'   => $visitor,
            'home_teamid'      => $home,
            'game_of_that_day' => $gameOfThatDay,
            'box_id'           => 42,
            'sort_order'       => $sortOrder,
            'recap_text'       => "Recap for game {$gameOfThatDay}.",
        ];
    }
}
