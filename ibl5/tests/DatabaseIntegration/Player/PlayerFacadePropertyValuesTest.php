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

    private const array ORPHAN_PROPERTIES = [
        'plr',
        'teamCity',
    ];

    private const array UNSYNCED_PROPERTIES = [
        'teamColor1',
        'teamColor2',
    ];

    private const array CALCULATED_PROPERTIES = [
        'currentSeasonSalary',
        'decoratedName',
        'nameStatusClass',
    ];

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

        self::assertSame(self::TEST_PID, $player->playerID);
        self::assertSame('Facade Test Player', $player->name);
        self::assertSame(27, $player->age);
        self::assertSame(1, $player->teamid);
        self::assertSame('PG', $player->position);
        self::assertSame(5, $player->yearsOfExperience);
        self::assertSame(3, $player->birdYears);
        self::assertSame(1, $player->contractCurrentYear);
        self::assertSame(3, $player->contractTotalYears);
        self::assertSame(1500, $player->contractYear1Salary);
        self::assertSame(1600, $player->contractYear2Salary);
        self::assertSame(0, $player->isRetired);
        self::assertSame(1, $player->ordinal);
        self::assertSame(0, $player->timeDroppedOnWaivers);
    }

    public function testWithPlrRowPopulatesAllSyncedProperties(): void
    {
        $row = $this->loadPlrRow(self::TEST_PID);
        $player = Player::withPlrRow($this->db, $row);

        self::assertSame(self::TEST_PID, $player->playerID);
        self::assertSame('Facade Test Player', $player->name);
        self::assertSame(27, $player->age);
        self::assertSame(1, $player->teamid);
        self::assertSame('PG', $player->position);
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

        self::assertSame(self::TEST_PID, $player->playerID);
        self::assertSame('Facade Test Player', $player->name);
        self::assertSame(2025, $player->historicalYear);
        self::assertSame('Metros', $player->teamName);
        self::assertSame(1500, $player->salaryJSB);
    }

    public function testOrphanPropertiesAreAlwaysNull(): void
    {
        $player = Player::withPlayerID($this->db, self::TEST_PID);

        foreach (self::ORPHAN_PROPERTIES as $prop) {
            self::assertNull(
                $player->$prop,
                "Orphan property \$player->$prop should be null (never synced from PlayerData)"
            );
        }
    }

    public function testGetterParityWithProperties(): void
    {
        $player = Player::withPlayerID($this->db, self::TEST_PID);
        $skip = array_merge(self::ORPHAN_PROPERTIES, self::UNSYNCED_PROPERTIES, self::CALCULATED_PROPERTIES);

        $reflection = new \ReflectionClass($player);
        $publicProps = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($publicProps as $prop) {
            $propName = $prop->getName();
            if (in_array($propName, $skip, true)) {
                continue;
            }

            $getterName = 'get' . ucfirst($propName);
            self::assertTrue(
                method_exists($player, $getterName),
                "Missing getter: Player::$getterName() for property \$$propName"
            );
            self::assertSame(
                $player->$propName,
                $player->$getterName(),
                "Getter parity failed: \$player->$propName !== \$player->$getterName()"
            );
        }
    }

    public function testGetterParityForCalculatedProperties(): void
    {
        $player = Player::withPlayerID($this->db, self::TEST_PID);

        self::assertSame($player->currentSeasonSalary, $player->getCurrentSeasonSalary());
        self::assertSame($player->decoratedName, $player->getDecoratedName());
        self::assertSame($player->nameStatusClass, $player->getNameStatusClass());
    }

    public function testGetterParityForOrphanProperties(): void
    {
        $player = Player::withPlayerID($this->db, self::TEST_PID);

        self::assertNull($player->getPlrRow());
        self::assertNull($player->getTeamCity());
    }

    public function testUnsyncedPropertiesGetterReturnsPlayerDataValue(): void
    {
        $player = Player::withPlayerID($this->db, self::TEST_PID);

        self::assertNull($player->teamColor1, 'Property is always null (never synced)');
        self::assertNotNull($player->getTeamColor1(), 'Getter reads from PlayerData (populated by repo)');

        self::assertNull($player->teamColor2, 'Property is always null (never synced)');
        self::assertNotNull($player->getTeamColor2(), 'Getter reads from PlayerData (populated by repo)');
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
