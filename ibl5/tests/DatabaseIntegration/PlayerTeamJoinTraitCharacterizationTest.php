<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Characterization tests for the player↔team JOIN column shape.
 *
 * These tests pin the contract that every repository site producing a
 * player row enriched with team data emits exactly the columns coming from
 * `PlayerTeamJoinQuery::playerWithTeamSelect()`:
 *   `teamname`, `color1`, `color2`  (from `ibl_team_info`)
 *   `p.*`                           (from `ibl_plr`)
 *
 * They do NOT pin teamid=… or t.team_city — the discriminating assertions
 * (`assertArrayNotHasKey('team_city', …)`) catch the realistic Phase 2
 * mistake of accidentally reaching for a wider column list.
 *
 * All five tests must be green BEFORE Phase 2 begins.  A red test here
 * means the fixture is wrong — do not "fix" it by starting the refactor.
 */
#[Group('database')]
class PlayerTeamJoinTraitCharacterizationTest extends DatabaseTestCase
{
    /** Real franchise seeded in ibl_team_info (New York Metros). */
    private const TEST_TID = 1;

    /**
     * Value passed to getDraftHistory() — copied verbatim from
     * TeamQueryRepositoryTest::testGetDraftHistoryReturnsPlayersDraftedByTeam.
     * The repository uses `WHERE p.draftedby LIKE ?`; without wildcards this
     * is an exact-match, so the fixture `draftedby` must equal this constant.
     */
    private const DRAFTED_BY = 'TestDraftTeam';

    protected function setUp(): void
    {
        parent::setUp();

        // 4040404 is the LeagueStartersRepository::getPlaceholderRow() sentinel pid.
        // A real row with this pid may already exist in the seeded data; delete it
        // before inserting so the unique-key on (pid, uuid) does not conflict.
        // Both this DELETE and the following INSERT are inside the test transaction
        // and will be rolled back by DatabaseTestCase::tearDown().
        $this->db->query('DELETE FROM ibl_plr WHERE pid = 4040404');

        // Player 900001 — on a real franchise (teamid=1).
        // Overrides satisfy every TeamQueryRepository filter simultaneously:
        // draftedby for getDraftHistory, cy≠cyt for FA roster, ordinal≤960 for
        // getHealthyAndInjuredPlayers*, injured=0 for getHealthyPlayers*,
        // salary_yr1≠0 for getAllPlayersUnderContract and ByPosition.
        $this->insertTestPlayer(900001, 'Test Player Alpha', [
            'teamid'     => self::TEST_TID,
            'pos'        => 'PG',
            'retired'    => 0,
            'injured'    => 0,
            'ordinal'    => 1,
            'cy'         => 1,
            'cyt'        => 3,
            'salary_yr1' => 1500,
            'draftedby'  => self::DRAFTED_BY,
            'draftyear'  => 2098,
            'draftround' => 1,
            'draftpickno' => 5,
        ]);

        // Player 900002 — Free Agents (teamid=0, seeded ibl_team_info row).
        // Boundary case for getAllPlayersExcludingTeam: excluded from TEST_TID
        // results and its teamname must be 'Free Agents'.
        $this->insertTestPlayer(900002, 'Test Player Beta', [
            'teamid'     => 0,
            'pos'        => 'PG',
            'retired'    => 0,
            'injured'    => 0,
            'ordinal'    => 1,
            'cy'         => 1,
            'cyt'        => 3,
            'salary_yr1' => 1500,
        ]);

        // Player 4040404 — sentinel pid used by LeagueStartersRepository::getPlaceholderRow().
        $this->insertTestPlayer(4040404, 'Placeholder Sentinel', [
            'teamid' => self::TEST_TID,
        ]);
    }

    /**
     * Exhaustive over all eight TeamQueryRepository sites converted in Phase 2.
     * Each closure is labelled so a failure names the offending method.
     */
    public function testAllTeamQueryJoinSitesReturnJoinedTeamColumns(): void
    {
        $repo = new \Team\TeamQueryRepository($this->db);
        $sites = [
            'getDraftHistory'                          => fn () => $repo->getDraftHistory(self::DRAFTED_BY),
            'getFreeAgencyRosterOrderedByName'         => fn () => $repo->getFreeAgencyRosterOrderedByName(self::TEST_TID),
            'getHealthyAndInjuredPlayersOrderedByName' => fn () => $repo->getHealthyAndInjuredPlayersOrderedByName(self::TEST_TID),
            'getHealthyPlayersOrderedByName'           => fn () => $repo->getHealthyPlayersOrderedByName(self::TEST_TID),
            'getAllPlayersUnderContract'               => fn () => $repo->getAllPlayersUnderContract(self::TEST_TID),
            'getPlayersUnderContractByPosition'        => fn () => $repo->getPlayersUnderContractByPosition(self::TEST_TID, 'PG'),
            'getRosterUnderContractOrderedByName'      => fn () => $repo->getRosterUnderContractOrderedByName(self::TEST_TID),
            'getRosterUnderContractOrderedByOrdinal'   => fn () => $repo->getRosterUnderContractOrderedByOrdinal(self::TEST_TID),
        ];

        foreach ($sites as $label => $call) {
            $rows = $call();
            $this->assertNotEmpty($rows, "$label returned no rows; fixture no longer satisfies its filters");
            $row = $rows[0];
            $this->assertArrayHasKey('teamname', $row, "$label lost the joined teamname alias");
            $this->assertArrayHasKey('color1', $row, "$label lost the joined color1 column");
            $this->assertArrayHasKey('color2', $row, "$label lost the joined color2 column");
            $this->assertArrayHasKey('name', $row, "$label lost the p.* expansion");
            $this->assertArrayNotHasKey('team_city', $row, "$label selected a wider team column list than the trait");
        }
    }

    /**
     * Pins the single FreeAgencyRepository site converted in Phase 3.
     * The double-negative path confirms the teamid predicate is preserved.
     */
    public function testFreeAgencyExcludingTeamReturnsJoinedTeamColumns(): void
    {
        $repo = new \FreeAgency\FreeAgencyRepository($this->db);
        $rows  = $repo->getAllPlayersExcludingTeam(self::TEST_TID);
        $byPid = array_column($rows, null, 'pid');
        $this->assertArrayHasKey(900002, $byPid, 'free agent excluded from getAllPlayersExcludingTeam');
        $this->assertArrayNotHasKey(900001, $byPid, 'player on the excluded team leaked into the result');
        $this->assertSame('Free Agents', $byPid[900002]['teamname']);
        $this->assertArrayHasKey('color1', $byPid[900002]);
        $this->assertArrayHasKey('color2', $byPid[900002]);
        $this->assertArrayNotHasKey('team_city', $byPid[900002]);
    }

    /**
     * Pins LeagueStartersRepository::getPlaceholderRow() converted in Phase 4.
     * No existing test covers this method.
     */
    public function testLeagueStartersPlaceholderRowReturnsJoinedTeamColumns(): void
    {
        $repo = new \LeagueStarters\LeagueStartersRepository($this->db);
        $row  = $repo->getPlaceholderRow();
        $this->assertIsArray($row, 'placeholder row disappeared');
        $this->assertSame(4040404, (int) $row['pid']);
        $this->assertArrayHasKey('teamname', $row);
        $this->assertArrayHasKey('color1', $row);
        $this->assertArrayHasKey('color2', $row);
        $this->assertArrayNotHasKey('team_city', $row);
    }

    /**
     * Boundary / empty-result path for Phase 2.
     * getPlayersUnderContractByPosition binds two parameters (team id + position);
     * a rewrite that drops or reorders a bind marker either throws or returns rows.
     */
    public function testPlayersUnderContractByUnknownPositionReturnsEmptyArray(): void
    {
        $repo = new \Team\TeamQueryRepository($this->db);
        $this->assertSame([], $repo->getPlayersUnderContractByPosition(self::TEST_TID, 'ZZ'));
    }

    /**
     * Structural discriminator: the shared trait SQL must use LEFT JOIN, alias
     * team_name as teamname, and must not select team_city.
     *
     * We reflect on PlayerLookupRepository rather than the trait class itself
     * because PHP's ReflectionMethod::invoke() requires an instance of the
     * *declaring* class — traits are copied into consumers at compile time, so
     * the consumer (not the trait) is the declaring class on the instance.
     * TeamQueryRepository does not use PlayerTeamJoinQuery until Phase 2, so
     * using it here would make this test red pre-impl.
     *
     * Deliberate, narrow deviation from phpunit-tests.md's "no Reflection for
     * private methods": `ibl_plr.teamid` is NOT NULL with an FK to
     * `ibl_team_info`, so every player row always has a matching team row and
     * LEFT JOIN and INNER JOIN return identical result sets. The LEFT-vs-INNER
     * invariant is therefore unobservable through any public API, and this is
     * the only place it is asserted for all 13 trait call sites at once. No
     * setAccessible() is used (banned outright; a no-op since PHP 8.1).
     */
    public function testPlayerTeamJoinTraitSelectUsesLeftJoin(): void
    {
        $repo   = new \Repositories\PlayerLookupRepository($this->db);
        $method = new \ReflectionMethod($repo::class, 'playerWithTeamSelect');
        /** @var string $sql */
        $sql    = $method->invoke($repo);
        $this->assertStringContainsString('LEFT JOIN `ibl_team_info`', $sql);
        $this->assertStringNotContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('t.team_name AS teamname', $sql);
        $this->assertStringNotContainsString('t.team_city', $sql);
    }
}
