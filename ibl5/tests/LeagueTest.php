<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use League;

/**
 * LeagueTest - Tests for League class constants and utility methods
 */
class LeagueTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;
        
        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                return false;
            }

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };
        
        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testLeagueCanBeInstantiated(): void
    {
        $league = new League($this->mockMysqliDb);
        
        $this->assertInstanceOf(League::class, $league);
    }

    public function testLeagueExtendsBaseMysqliRepository(): void
    {
        $league = new League($this->mockMysqliDb);
        
        $this->assertInstanceOf(\BaseMysqliRepository::class, $league);
    }

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

    public function testSoftCapMaxIsCorrect(): void
    {
        $this->assertEquals(5000, League::SOFT_CAP_MAX);
    }

    public function testHardCapMaxIsCorrect(): void
    {
        $this->assertEquals(7000, League::HARD_CAP_MAX);
    }

    public function testFreeAgentsTeamIdIsZero(): void
    {
        $this->assertEquals(0, League::FREE_AGENTS_TEAMID);
    }

    // ============================================
    // FORMAT TIDS FOR SQL QUERY TESTS
    // ============================================

    public function testFormatTidsForSqlQueryFormatsCorrectly(): void
    {
        $league = new League($this->mockMysqliDb);
        
        $result = $league->formatTidsForSqlQuery([1, 2, 3]);
        
        $this->assertEquals("1','2','3", $result);
    }

    public function testFormatTidsForSqlQueryHandlesSingleTeam(): void
    {
        $league = new League($this->mockMysqliDb);
        
        $result = $league->formatTidsForSqlQuery([5]);
        
        $this->assertEquals('5', $result);
    }

    public function testFormatTidsForSqlQueryHandlesEmptyArray(): void
    {
        $league = new League($this->mockMysqliDb);
        
        $result = $league->formatTidsForSqlQuery([]);
        
        $this->assertEquals('', $result);
    }

    // ============================================
    // ALL STAR POSITION CONSTANTS TESTS
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
        $league1 = new League($this->mockMysqliDb);
        $league2 = new League($this->mockMysqliDb);
        
        $this->assertNotSame($league1, $league2);
    }
}
