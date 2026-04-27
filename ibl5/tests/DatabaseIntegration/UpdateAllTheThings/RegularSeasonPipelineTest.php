<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

class RegularSeasonPipelineTest extends PipelineIntegrationTestCase
{
    public function testRegularSeasonFullPipeline(): void
    {
        $this->updateSetting('Current Season Phase', 'Regular Season');
        $this->updateSetting('Current Season Ending Year', '2026');
        $this->seedSimDates(4, '2026-01-05', '2026-01-09');
        $this->seedSimDates(5, '2026-01-10', '2026-01-15');
        $this->seedLeagueConfig(2026);

        $season = $this->buildSeason('Regular Season', 2026);

        // Jan 11 → date_slot 103, Jan 12 → date_slot 104
        $schPath = $this->buildSchFile([
            ['date_slot' => 103, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 105, 'home_score' => 98],
            ['date_slot' => 103, 'game_index' => 1, 'visitor' => 3, 'home' => 4, 'visitor_score' => 110, 'home_score' => 102],
            ['date_slot' => 104, 'game_index' => 0, 'visitor' => 5, 'home' => 6, 'visitor_score' => 99, 'home_score' => 101],
            ['date_slot' => 104, 'game_index' => 1, 'visitor' => 7, 'home' => 8, 'visitor_score' => 85, 'home_score' => 90],
        ]);
        $this->buildPlrFile([
            ['pid' => 200001, 'name' => 'RS Player A', 'teamid' => 1, 'ordinal' => 1],
            ['pid' => 200002, 'name' => 'RS Player B', 'teamid' => 2, 'ordinal' => 2],
            ['pid' => 200003, 'name' => 'RS Player C', 'teamid' => 3, 'ordinal' => 3],
            ['pid' => 200004, 'name' => 'RS Player D', 'teamid' => 4, 'ordinal' => 4],
        ]);
        $scoPath = $this->buildScoFile();

        $pipeline = $this->buildPipeline($season, $schPath, $scoPath);
        $results = $this->runPipeline($pipeline);

        $this->assertZeroPipelineErrors($pipeline, $results);

        self::assertSame(4, $this->countRows('ibl_schedule', "game_date LIKE '2026-01-%'"));

        $stmt = $this->db->prepare("SELECT wins FROM ibl_standings WHERE teamid = ?");
        self::assertNotFalse($stmt);
        $teamId = 1;
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);
        self::assertGreaterThan(0, $row['wins'], 'Team 1 (visitor, won 105-98) should have wins > 0');

        $cleanupResult = $this->findResultByLabel($results, 'Preseason data cleaned');
        self::assertNotNull($cleanupResult);
        self::assertStringContainsString('Not HEAT phase', $cleanupResult->detail);

        $eosResult = $this->findResultByLabel($results, 'End-of-season imports');
        self::assertNotNull($eosResult);
        self::assertTrue($eosResult->success);

        $snapshotCount = $this->countRows('ibl_plr_snapshots', "season_year = 2026");
        self::assertGreaterThan(0, $snapshotCount, 'Mid-season PLR snapshot should have been created');

        $histCount = $this->countRows('ibl_hist', '1=1');
        self::assertGreaterThan(0, $histCount, 'ibl_hist should have been refreshed');
    }
}
