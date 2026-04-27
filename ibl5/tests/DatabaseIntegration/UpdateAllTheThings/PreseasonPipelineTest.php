<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

class PreseasonPipelineTest extends PipelineIntegrationTestCase
{
    public function testPreseasonScheduleUsesRealYears(): void
    {
        $this->updateSetting('Current Season Phase', 'Preseason');
        $this->updateSetting('Current Season Ending Year', '2026');
        $this->seedSimDates(1, '2025-11-01', '2025-11-05');
        $this->seedLeagueConfig(2026);

        $season = $this->buildSeason('Preseason', 2026);

        // Nov 3 date_slot: monthOffset=1 (Nov), day=3 → 1*31 + 2 = 33
        $schPath = $this->buildSchFile([
            ['date_slot' => 33, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 95, 'home_score' => 100],
            ['date_slot' => 33, 'game_index' => 1, 'visitor' => 3, 'home' => 4, 'visitor_score' => 88, 'home_score' => 92],
        ]);
        $this->buildPlrFile([
            ['pid' => 200001, 'name' => 'Preseason Player A', 'teamid' => 1, 'ordinal' => 1],
            ['pid' => 200002, 'name' => 'Preseason Player B', 'teamid' => 2, 'ordinal' => 2],
        ]);
        $scoPath = $this->buildScoFile();

        $pipeline = $this->buildPipeline($season, $schPath, $scoPath);
        $results = $this->runPipeline($pipeline);

        $this->assertZeroPipelineErrors($pipeline, $results);

        $scheduleCount = $this->countRows('ibl_schedule', "game_date LIKE '2025-%'");
        self::assertSame(2, $scheduleCount, 'Schedule should have 2 games with real 2025 dates');

        self::assertSame(0, $this->countRows('ibl_schedule', "game_date LIKE '9998-%'"),
            'No sentinel year 9998 dates should exist');

        $cleanupResult = $this->findResultByLabel($results, 'Preseason data cleaned');
        self::assertNotNull($cleanupResult);
        self::assertStringContainsString('Not HEAT phase', $cleanupResult->detail);
    }

    public function testPlayoffsPipelineCompletes(): void
    {
        $this->updateSetting('Current Season Phase', 'Playoffs');
        $this->updateSetting('Current Season Ending Year', '2026');
        $this->seedSimDates(20, '2026-06-01', '2026-06-05');
        $this->seedLeagueConfig(2026);

        $season = $this->buildSeason('Playoffs', 2026);

        $schPath = $this->buildSchFile([]);
        $this->buildPlrFile([
            ['pid' => 200001, 'name' => 'Playoff Player A', 'teamid' => 1, 'ordinal' => 1],
            ['pid' => 200002, 'name' => 'Playoff Player B', 'teamid' => 2, 'ordinal' => 2],
        ]);
        $scoPath = $this->buildScoFile();

        $pipeline = $this->buildPipeline($season, $schPath, $scoPath);
        $results = $this->runPipeline($pipeline);

        $this->assertZeroPipelineErrors($pipeline, $results);

        $cleanupResult = $this->findResultByLabel($results, 'Preseason data cleaned');
        self::assertNotNull($cleanupResult);
        self::assertStringContainsString('Not HEAT phase', $cleanupResult->detail);
    }
}
