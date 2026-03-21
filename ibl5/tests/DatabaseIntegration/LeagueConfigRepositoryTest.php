<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use LeagueConfig\LeagueConfigRepository;

/**
 * Tests LeagueConfigRepository against real MariaDB — season config upserts,
 * config lookups, and franchise team mapping.
 */
class LeagueConfigRepositoryTest extends DatabaseTestCase
{
    private LeagueConfigRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new LeagueConfigRepository($this->db);
    }

    // ── hasConfigForSeason ──────────────────────────────────────

    public function testHasConfigForSeasonReturnsFalseWhenNoConfig(): void
    {
        $result = $this->repo->hasConfigForSeason(9999);

        self::assertFalse($result);
    }

    public function testHasConfigForSeasonReturnsTrueAfterUpsert(): void
    {
        $this->repo->upsertSeasonConfig(2099, [
            $this->makeConfigRow(1, 'Metros'),
        ]);

        $result = $this->repo->hasConfigForSeason(2099);

        self::assertTrue($result);
    }

    // ── upsertSeasonConfig ──────────────────────────────────────

    public function testUpsertSeasonConfigInsertsRows(): void
    {
        $affected = $this->repo->upsertSeasonConfig(2099, [
            $this->makeConfigRow(1, 'Metros'),
            $this->makeConfigRow(2, 'Stars'),
        ]);

        self::assertSame(2, $affected);

        $rows = $this->repo->getConfigForSeason(2099);
        self::assertCount(2, $rows);
        self::assertSame(1, $rows[0]['team_slot']);
        self::assertSame('Metros', $rows[0]['team_name']);
        self::assertSame(2, $rows[1]['team_slot']);
        self::assertSame('Stars', $rows[1]['team_name']);
    }

    public function testUpsertSeasonConfigUpdatesExistingRows(): void
    {
        // First insert
        $this->repo->upsertSeasonConfig(2099, [
            $this->makeConfigRow(1, 'Metros'),
        ]);

        // Update same slot with different team_name
        $this->repo->upsertSeasonConfig(2099, [
            $this->makeConfigRow(1, 'Stars'),
        ]);

        $rows = $this->repo->getConfigForSeason(2099);
        self::assertCount(1, $rows);
        self::assertSame('Stars', $rows[0]['team_name']);
    }

    // ── getConfigForSeason ──────────────────────────────────────

    public function testGetConfigForSeasonReturnsEmptyForUnknownYear(): void
    {
        $result = $this->repo->getConfigForSeason(9999);

        self::assertSame([], $result);
    }

    // ── getFranchiseTeamsBySeason ────────────────────────────────

    public function testGetFranchiseTeamsBySeasonReturnsMapKeyedByFranchiseId(): void
    {
        // Insert known franchise_season rows for a test year
        $this->insertFranchiseSeasonRow(1, 2099, 'Metros');
        $this->insertFranchiseSeasonRow(2, 2099, 'Stars');

        $result = $this->repo->getFranchiseTeamsBySeason(2099);

        self::assertCount(2, $result);
        self::assertArrayHasKey(1, $result);
        self::assertSame('Metros', $result[1]);
        self::assertArrayHasKey(2, $result);
        self::assertSame('Stars', $result[2]);
    }

    public function testGetFranchiseTeamsBySeasonReturnsEmptyForUnknownYear(): void
    {
        $result = $this->repo->getFranchiseTeamsBySeason(9999);

        self::assertSame([], $result);
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Build a config row array for upsertSeasonConfig().
     *
     * @return array{team_slot: int, team_name: string, conference: string, division: string, playoff_qualifiers_per_conf: int, playoff_round1_format: string, playoff_round2_format: string, playoff_round3_format: string, playoff_round4_format: string, team_count: int}
     */
    private function makeConfigRow(int $teamSlot, string $teamName): array
    {
        return [
            'team_slot' => $teamSlot,
            'team_name' => $teamName,
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'playoff_qualifiers_per_conf' => 8,
            'playoff_round1_format' => 'bo7',
            'playoff_round2_format' => 'bo7',
            'playoff_round3_format' => 'bo7',
            'playoff_round4_format' => 'bo7',
            'team_count' => 28,
        ];
    }
}
