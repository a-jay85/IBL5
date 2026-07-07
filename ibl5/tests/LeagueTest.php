<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use League\League;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * LeagueTest - Tests for League class constants and utility methods
 */
class LeagueTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    // ============================================
    // CONSTANT TESTS
    // ============================================

    public function testConferenceNamesContainsTwoConferences(): void
    {
        $this->assertCount(2, League::CONFERENCE_NAMES);
        $this->assertContains('Eastern', League::CONFERENCE_NAMES);
        $this->assertContains('Western', League::CONFERENCE_NAMES);
    }

    public function testDivisionNamesContainsFourDivisions(): void
    {
        $this->assertCount(4, League::DIVISION_NAMES);
        $this->assertContains('Atlantic', League::DIVISION_NAMES);
        $this->assertContains('Central', League::DIVISION_NAMES);
        $this->assertContains('Midwest', League::DIVISION_NAMES);
        $this->assertContains('Pacific', League::DIVISION_NAMES);
    }

    public function testEasternConferenceContains14Teams(): void
    {
        $this->assertCount(14, League::EASTERN_CONFERENCE_TEAMIDS);
    }

    public function testWesternConferenceContains14Teams(): void
    {
        $this->assertCount(14, League::WESTERN_CONFERENCE_TEAMIDS);
    }

    public function testSoftCapMaxIsLessThanHardCapMax(): void
    {
        $this->assertLessThan(League::HARD_CAP_MAX, League::SOFT_CAP_MAX);
    }

    public function testFreeAgentsTeamIdIsNotARealFranchise(): void
    {
        $this->assertFalse(League::isRealFranchise(League::FREE_AGENTS_TEAMID));
    }

    public function testSpecialTeamIdsAreNotRealFranchises(): void
    {
        $this->assertFalse(League::isRealFranchise(League::ROOKIES_TEAMID));
        $this->assertFalse(League::isRealFranchise(League::SOPHOMORES_TEAMID));
        $this->assertFalse(League::isRealFranchise(League::ALL_STAR_AWAY_TEAMID));
        $this->assertFalse(League::isRealFranchise(League::ALL_STAR_HOME_TEAMID));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('realFranchiseProvider')]
    public function testIsRealFranchise(int $teamId, bool $expected): void
    {
        self::assertSame($expected, League::isRealFranchise($teamId));
    }

    /** @return array<string, array{int, bool}> */
    public static function realFranchiseProvider(): array
    {
        return [
            'free agents' => [0, false],
            'first franchise' => [1, true],
            'last franchise' => [28, true],
            'above max' => [29, false],
            'rookies' => [40, false],
            'sophomores' => [41, false],
            'all-star away' => [50, false],
            'all-star home' => [51, false],
            'negative' => [-1, false],
        ];
    }

    // ============================================
    // FORMAT TIDS FOR SQL QUERY TESTS
    // ============================================

    public function testFormatTidsForSqlQueryFormatsCorrectly(): void
    {
        $league = new League($this->mockDb);
        
        $result = $league->formatTidsForSqlQuery([1, 2, 3]);
        
        $this->assertSame("1','2','3", $result);
    }

    public function testFormatTidsForSqlQueryHandlesSingleTeam(): void
    {
        $league = new League($this->mockDb);
        
        $result = $league->formatTidsForSqlQuery([5]);
        
        $this->assertSame('5', $result);
    }

    public function testFormatTidsForSqlQueryHandlesEmptyArray(): void
    {
        $league = new League($this->mockDb);
        
        $result = $league->formatTidsForSqlQuery([]);
        
        $this->assertSame('', $result);
    }

    // ============================================
    // ALL-STAR CONFERENCE SPLIT CHARACTERIZATION TESTS
    // ============================================

    /**
     * Regression lock: ASG conference split MUST use WESTERN_CONFERENCE_TEAMIDS,
     * not ibl_standings.conference.
     *
     * getAllStarCandidatesResult('WC-*') must embed the Western teamid constants
     * directly in the SQL WHERE clause — never join against ibl_standings.
     * A teamid in WESTERN_CONFERENCE_TEAMIDS is Western even if the DB's conference
     * column disagrees (the DB column is display metadata, not authoritative).
     */
    public function testGetAllStarCandidatesResultWestUsesWesternConferenceTeamids(): void
    {
        $league = new League($this->mockDb);

        $league->getAllStarCandidatesResult('WC-CF');

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries, 'Expected exactly one query for a single candidate lookup');
        $query = $queries[0];

        // The query must NOT derive conference from ibl_standings — that column is
        // display metadata, not the authoritative conference list.
        $this->assertStringNotContainsStringIgnoringCase(
            'ibl_standings',
            $query,
            'Conference bucketing must use WESTERN_CONFERENCE_TEAMIDS, not ibl_standings.conference'
        );

        // At least one Western teamid constant must appear verbatim in the SQL.
        $foundWestTid = false;
        foreach (League::WESTERN_CONFERENCE_TEAMIDS as $tid) {
            if (str_contains($query, (string) $tid)) {
                $foundWestTid = true;
                break;
            }
        }
        $this->assertTrue(
            $foundWestTid,
            'SQL must embed teamids from WESTERN_CONFERENCE_TEAMIDS directly in the WHERE clause'
        );
    }

    public function testGetAllStarCandidatesResultEastUsesEasternConferenceTeamids(): void
    {
        $league = new League($this->mockDb);

        $league->getAllStarCandidatesResult('EC-CB');

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $query = $queries[0];

        $this->assertStringNotContainsStringIgnoringCase('ibl_standings', $query);

        $foundEastTid = false;
        foreach (League::EASTERN_CONFERENCE_TEAMIDS as $tid) {
            if (str_contains($query, (string) $tid)) {
                $foundEastTid = true;
                break;
            }
        }
        $this->assertTrue(
            $foundEastTid,
            'SQL must embed teamids from EASTERN_CONFERENCE_TEAMIDS directly in the WHERE clause'
        );
    }

    public function testGetAllStarCandidatesResultWestExcludesEasternTeamids(): void
    {
        $league = new League($this->mockDb);
        $league->getAllStarCandidatesResult('WC-CF');

        // Spot-check: teamid 1 is Eastern — must not appear in the Western query's
        // teamid list. (It could appear in returned player data, but not in the IN clause.)
        // We check the WESTERN constant itself doesn't contain Eastern ids.
        $this->assertNotContains(
            League::EASTERN_CONFERENCE_TEAMIDS[0],
            League::WESTERN_CONFERENCE_TEAMIDS,
            'WESTERN_CONFERENCE_TEAMIDS must not overlap with EASTERN_CONFERENCE_TEAMIDS'
        );

        // The constants partition all 28 real franchises with no overlap.
        $all = array_merge(League::EASTERN_CONFERENCE_TEAMIDS, League::WESTERN_CONFERENCE_TEAMIDS);
        sort($all);
        $this->assertSame(range(1, 28), $all, 'East + West teamids must cover exactly franchises 1-28 with no gaps or overlaps');
    }

    // ============================================
    // ALL-STAR POSITION CONSTANTS TESTS
    // ============================================

    public function testAllStarBackcourtPositionsContainsPgAndSg(): void
    {
        $this->assertStringContainsString('PG', League::ALL_STAR_BACKCOURT_POSITIONS);
        $this->assertStringContainsString('SG', League::ALL_STAR_BACKCOURT_POSITIONS);
    }

    public function testAllStarFrontcourtPositionsContainsCenterAndForwards(): void
    {
        $this->assertStringContainsString('C', League::ALL_STAR_FRONTCOURT_POSITIONS);
        $this->assertStringContainsString('SF', League::ALL_STAR_FRONTCOURT_POSITIONS);
        $this->assertStringContainsString('PF', League::ALL_STAR_FRONTCOURT_POSITIONS);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleLeagueInstancesCanBeCreated(): void
    {
        $league1 = new League($this->mockDb);
        $league2 = new League($this->mockDb);
        
        $this->assertNotSame($league1, $league2);
    }
}
