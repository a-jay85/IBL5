<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Player;

use PHPUnit\Framework\Attributes\Group;
use Player\Player;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class PlayerFacadePropertyValuesTest extends DatabaseTestCase
{
    private const int TEST_PID = 200_000_001;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertTestPlayer(self::TEST_PID, 'Facade Test Player', [
            'age' => 27,
            'teamid' => 1,
            'pos' => 'PG',
            'exp' => 5,
            'bird' => 3,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 1500,
            'salary_yr2' => 1600,
            'retired' => 0,
            'ordinal' => 1,
            'droptime' => 0,
        ]);
    }

    public function testWithPlayerIDPopulatesAllSyncedProperties(): void
    {
        $player = Player::withPlayerID($this->db, self::TEST_PID);

        self::assertSame(self::TEST_PID, $player->getPlayerID());
        self::assertSame('Facade Test Player', $player->getName());
        self::assertSame(27, $player->getAge());
        self::assertSame(1, $player->getTeamid());
        self::assertSame('PG', $player->getPosition());
        self::assertSame(5, $player->getYearsOfExperience());
        self::assertSame(3, $player->getBirdYears());
        self::assertSame(1, $player->getContractCurrentYear());
        self::assertSame(3, $player->getContractTotalYears());
        self::assertSame(1500, $player->getContractYear1Salary());
        self::assertSame(1600, $player->getContractYear2Salary());
        self::assertSame(0, $player->getIsRetired());
        self::assertSame(1, $player->getOrdinal());
        self::assertSame(0, $player->getTimeDroppedOnWaivers());
    }

    public function testWithPlrRowPopulatesAllSyncedProperties(): void
    {
        $row = $this->loadPlrRow(self::TEST_PID);
        $player = Player::withPlrRow($this->db, $row);

        self::assertSame(self::TEST_PID, $player->getPlayerID());
        self::assertSame('Facade Test Player', $player->getName());
        self::assertSame(27, $player->getAge());
        self::assertSame(1, $player->getTeamid());
        self::assertSame('PG', $player->getPosition());
    }

    public function testWithHistoricalPlrRowPopulatesExpectedSubset(): void
    {
        $this->insertHistRow(self::TEST_PID, 'Facade Test Player', 2025, [
            'team' => 'Metros',
            'teamid' => 1,
            'salary' => 1500,
        ]);

        $histRow = $this->loadHistRow(self::TEST_PID, 2025);
        $player = Player::withHistoricalPlrRow($this->db, $histRow);

        self::assertSame(self::TEST_PID, $player->getPlayerID());
        self::assertSame('Facade Test Player', $player->getName());
        self::assertSame(2025, $player->getHistoricalYear());
        self::assertSame('Metros', $player->getTeamName());
        self::assertSame(1500, $player->getSalaryJSB());
    }

    public function testGetterParityForOrphanProperties(): void
    {
        $player = Player::withPlayerID($this->db, self::TEST_PID);

        self::assertNull($player->getPlrRow());
    }

    public function testCalculatedGettersReturnValues(): void
    {
        $player = Player::withPlayerID($this->db, self::TEST_PID);

        self::assertNotNull($player->getCurrentSeasonSalary());
        self::assertNotNull($player->getDecoratedName());
        self::assertNotNull($player->getNameStatusClass());
    }

    public function testColorGettersReadFromPlayerData(): void
    {
        $player = Player::withPlayerID($this->db, self::TEST_PID);

        self::assertNotNull($player->getTeamColor1(), 'getTeamColor1() reads from PlayerData (populated by repo)');
        self::assertNotNull($player->getTeamColor2(), 'getTeamColor2() reads from PlayerData (populated by repo)');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPlrRow(int $pid): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2 '
            . 'FROM ibl_plr p LEFT JOIN ibl_team_info t ON p.teamid = t.teamid '
            . 'WHERE p.pid = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertIsArray($row);
        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadHistRow(int $pid, int $year): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ibl_hist WHERE pid = ? AND year = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $pid, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertIsArray($row);
        return $row;
    }
}
