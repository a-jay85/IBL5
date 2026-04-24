<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Api\Repository\ApiInjuriesRepository;

/**
 * Tests ApiInjuriesRepository against real MariaDB —
 * injured player listing with team data from ibl_plr + ibl_team_info.
 */
class ApiInjuriesRepositoryTest extends DatabaseTestCase
{
    private ApiInjuriesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ApiInjuriesRepository($this->db);
    }

    public function testGetInjuredPlayersReturnsInjuredPlayersOnly(): void
    {
        // Insert an injured player
        $this->insertTestPlayer(200000070, 'DB Test Injured Player', [
            'injured' => 5,
            'dc_can_play_in_game' => 1,
            'teamid' => 1,
        ]);

        // Insert a healthy player
        $this->insertTestPlayer(200000071, 'DB Test Healthy Player', [
            'injured' => 0,
            'dc_can_play_in_game' => 1,
            'teamid' => 1,
        ]);

        $result = $this->repo->getInjuredPlayers();

        $names = array_column($result, 'name');

        self::assertContains('DB Test Injured Player', $names);
        self::assertNotContains('DB Test Healthy Player', $names);
    }

    public function testGetInjuredPlayersExcludesInactivePlayers(): void
    {
        // Insert injured but inactive player (dc_can_play_in_game = 0)
        $this->insertTestPlayer(200000072, 'DB Test Inactive Injured', [
            'injured' => 3,
            'dc_can_play_in_game' => 0,
            'teamid' => 1,
        ]);

        $result = $this->repo->getInjuredPlayers();

        $names = array_column($result, 'name');

        self::assertNotContains('DB Test Inactive Injured', $names);
    }

    public function testGetInjuredPlayersIncludesTeamData(): void
    {
        $this->insertTestPlayer(200000073, 'DB Test Injured With Team', [
            'injured' => 2,
            'dc_can_play_in_game' => 1,
            'teamid' => 1,
        ]);

        $result = $this->repo->getInjuredPlayers();

        $matching = array_filter(
            $result,
            static fn (array $row): bool => $row['name'] === 'DB Test Injured With Team',
        );

        self::assertNotEmpty($matching);
        $player = array_values($matching)[0];

        self::assertArrayHasKey('player_uuid', $player);
        self::assertArrayHasKey('teamid', $player);
        self::assertArrayHasKey('team_uuid', $player);
        self::assertArrayHasKey('team_city', $player);
        self::assertArrayHasKey('team_name', $player);
        self::assertSame(1, $player['teamid']);
    }

    public function testGetInjuredPlayersOrderedByInjurySeverityDesc(): void
    {
        $this->insertTestPlayer(200000074, 'DB Test Mild Injury', [
            'injured' => 1,
            'dc_can_play_in_game' => 1,
            'teamid' => 1,
        ]);

        $this->insertTestPlayer(200000075, 'DB Test Severe Injury', [
            'injured' => 10,
            'dc_can_play_in_game' => 1,
            'teamid' => 1,
        ]);

        $result = $this->repo->getInjuredPlayers();

        $injuries = array_column($result, 'injured');

        // Should be ordered DESC
        for ($i = 1; $i < count($injuries); $i++) {
            self::assertGreaterThanOrEqual($injuries[$i], $injuries[$i - 1]);
        }
    }

    // ── Negative path ───────────────────────────────────────────

    public function testGetInjuredPlayersReturnsEmptyWhenNoPlayersInjured(): void
    {
        // Clear all injuries within the rolled-back transaction
        $this->db->query('UPDATE ibl_plr SET injured = 0 WHERE 1=1');

        $result = $this->repo->getInjuredPlayers();

        self::assertSame([], $result);
    }
}
