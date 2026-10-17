<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeSuggestions\TeamModeRepository;
use TradeSuggestions\TeamModeSetting;

/**
 * Tests TeamModeRepository against real MariaDB: the ibl_team_info.trade_mode
 * read/write round trip and the Auto fallbacks.
 */
#[Group('database')]
class TeamModeRepositoryTest extends DatabaseTestCase
{
    private const UNKNOWN_VALUE_TEAMID = 99990;

    private TeamModeRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamModeRepository($this->db);
    }

    public function testSaveAndReadSettingRoundTrip(): void
    {
        $this->db->query("UPDATE ibl_team_info SET trade_mode = 'auto' WHERE teamid IN (1, 2)");

        $this->repo->saveSetting(1, TeamModeSetting::Rebuild);

        self::assertSame(TeamModeSetting::Rebuild, $this->repo->getStoredSetting(1));
        self::assertSame(TeamModeSetting::Auto, $this->repo->getStoredSetting(2));
    }

    public function testUnknownStoredValueReadsAsAuto(): void
    {
        $this->db->query("SET SESSION sql_mode = ''");
        $this->insertRow('ibl_team_info', [
            'teamid' => self::UNKNOWN_VALUE_TEAMID,
            'team_name' => 'ModeTest',
            'trade_mode' => '',
        ]);

        $stored = $this->db->query(
            'SELECT trade_mode FROM ibl_team_info WHERE teamid = ' . self::UNKNOWN_VALUE_TEAMID
        );
        self::assertInstanceOf(\mysqli_result::class, $stored);
        self::assertSame(['trade_mode' => ''], $stored->fetch_assoc());

        self::assertSame(TeamModeSetting::Auto, $this->repo->getStoredSetting(self::UNKNOWN_VALUE_TEAMID));
    }

    public function testMissingTeamReadsAsAuto(): void
    {
        self::assertSame(TeamModeSetting::Auto, $this->repo->getStoredSetting(99999));
    }
}
