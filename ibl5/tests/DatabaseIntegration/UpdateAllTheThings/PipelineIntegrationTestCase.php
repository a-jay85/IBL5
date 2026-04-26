<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use Boxscore\BoxscoreView;
use JsbParser\JsbImportRepository;
use JsbParser\JsbImportService;
use JsbParser\PlayerIdResolver;
use PlrParser\PlrParserRepository;
use PlrParser\PlrParserService;
use SavedDepthChart\SavedDepthChartRepository;
use Season\Season;
use Services\CommonMysqliRepository;
use Shared\SharedRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Steps;
use Updater\UpdaterService;
use Updater\StepResult;
use Utilities\SchFileParser;

abstract class PipelineIntegrationTestCase extends DatabaseTestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/ibl5_pipeline_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    protected function updateSetting(string $name, string $value): void
    {
        $stmt = $this->db->prepare("UPDATE ibl_settings SET value = ? WHERE name = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $value, $name);
        $stmt->execute();
        $stmt->close();
    }

    protected function seedSimDates(int $sim, string $startDate, string $endDate): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ibl_sim_dates (sim, start_date, end_date) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date)"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('iss', $sim, $startDate, $endDate);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * @param array<int, array{conference: string, division: string}> $overrides teamid => assignment
     */
    protected function seedLeagueConfig(int $endingYear, array $overrides = []): void
    {
        $defaults = [
            1 => ['conference' => 'Eastern', 'division' => 'Atlantic'],
            2 => ['conference' => 'Western', 'division' => 'Pacific'],
            3 => ['conference' => 'Eastern', 'division' => 'Central'],
            4 => ['conference' => 'Eastern', 'division' => 'Central'],
            5 => ['conference' => 'Eastern', 'division' => 'Atlantic'],
            6 => ['conference' => 'Eastern', 'division' => 'Atlantic'],
            7 => ['conference' => 'Eastern', 'division' => 'Atlantic'],
            8 => ['conference' => 'Eastern', 'division' => 'Atlantic'],
            9 => ['conference' => 'Western', 'division' => 'Pacific'],
            10 => ['conference' => 'Western', 'division' => 'Midwest'],
            11 => ['conference' => 'Western', 'division' => 'Midwest'],
            12 => ['conference' => 'Eastern', 'division' => 'Central'],
            13 => ['conference' => 'Western', 'division' => 'Midwest'],
            14 => ['conference' => 'Eastern', 'division' => 'Central'],
            15 => ['conference' => 'Western', 'division' => 'Midwest'],
            16 => ['conference' => 'Western', 'division' => 'Pacific'],
            17 => ['conference' => 'Eastern', 'division' => 'Atlantic'],
            18 => ['conference' => 'Eastern', 'division' => 'Central'],
            19 => ['conference' => 'Western', 'division' => 'Pacific'],
            20 => ['conference' => 'Western', 'division' => 'Pacific'],
            21 => ['conference' => 'Western', 'division' => 'Midwest'],
            22 => ['conference' => 'Eastern', 'division' => 'Central'],
            23 => ['conference' => 'Western', 'division' => 'Pacific'],
            24 => ['conference' => 'Eastern', 'division' => 'Atlantic'],
            25 => ['conference' => 'Eastern', 'division' => 'Central'],
            26 => ['conference' => 'Eastern', 'division' => 'Central'],
            27 => ['conference' => 'Western', 'division' => 'Midwest'],
            28 => ['conference' => 'Western', 'division' => 'Pacific'],
        ];

        $assignments = array_replace($defaults, $overrides);

        $stmt = $this->db->prepare(
            "INSERT INTO ibl_league_config
                (team_slot, team_name, conference, division, season_ending_year,
                 playoff_qualifiers_per_conf, playoff_round1_format, playoff_round2_format,
                 playoff_round3_format, playoff_round4_format, team_count)
             VALUES (?, ?, ?, ?, ?, 8, '2-2-1', '2-2-1', '2-2-1', '2-2-1', 28)
             ON DUPLICATE KEY UPDATE conference = VALUES(conference), division = VALUES(division)"
        );
        self::assertNotFalse($stmt);

        $teamNames = $this->fetchTeamNames();

        foreach ($assignments as $teamId => $assignment) {
            $teamName = $teamNames[$teamId] ?? "Team {$teamId}";
            $stmt->bind_param(
                'isssi',
                $teamId,
                $teamName,
                $assignment['conference'],
                $assignment['division'],
                $endingYear,
            );
            $stmt->execute();
        }

        $stmt->close();
    }

    /**
     * @return array<int, string>
     */
    private function fetchTeamNames(): array
    {
        $result = $this->db->query("SELECT teamid, team_name FROM ibl_team_info WHERE teamid BETWEEN 1 AND 28");
        self::assertNotFalse($result);

        $names = [];
        while ($row = $result->fetch_assoc()) {
            $names[(int) $row['teamid']] = (string) $row['team_name'];
        }
        $result->free();

        return $names;
    }

    /**
     * Build a valid 80,000-byte .sch file with the specified games.
     *
     * @param list<array{date_slot: int, game_index: int, visitor: int, home: int, visitor_score: int, home_score: int}> $games
     */
    protected function buildSchFile(array $games = []): string
    {
        $empty = str_repeat('0   0     ', SchFileParser::SLOTS_PER_DATE);
        $data = str_repeat($empty, (int) (SchFileParser::FILE_SIZE / (SchFileParser::SLOTS_PER_DATE * SchFileParser::RECORD_SIZE)));

        foreach ($games as $game) {
            $offset = ($game['date_slot'] * SchFileParser::SLOTS_PER_DATE + $game['game_index']) * SchFileParser::RECORD_SIZE;

            $home = str_pad((string) $game['home'], 2, '0', STR_PAD_LEFT);
            $visitor = (string) $game['visitor'];
            $teamsField = str_pad($visitor . $home, SchFileParser::TEAMS_FIELD_SIZE);

            if ($game['visitor_score'] === 0 && $game['home_score'] === 0) {
                $scoresField = str_pad('0', SchFileParser::SCORES_FIELD_SIZE);
            } else {
                $homeScore = str_pad((string) $game['home_score'], 3, '0', STR_PAD_LEFT);
                $visitorScore = (string) $game['visitor_score'];
                $scoresField = str_pad($visitorScore . $homeScore, SchFileParser::SCORES_FIELD_SIZE);
            }

            $record = $teamsField . $scoresField;
            $data = substr_replace($data, $record, $offset, SchFileParser::RECORD_SIZE);
        }

        self::assertSame(SchFileParser::FILE_SIZE, strlen($data));

        $path = $this->tempDir . '/IBL5.sch';
        file_put_contents($path, $data);
        return $path;
    }

    /**
     * Build a valid .plr file with CRLF-separated 607-byte records.
     *
     * @param list<array{pid: int, name: string, teamid: int, ordinal: int}> $players
     */
    protected function buildPlrFile(array $players): string
    {
        $lines = [];
        foreach ($players as $player) {
            $line = str_repeat(' ', 607);

            $line = substr_replace($line, str_pad((string) $player['ordinal'], 4, ' ', STR_PAD_LEFT), 0, 4);
            $line = substr_replace($line, str_pad($player['name'], 32), 4, 32);
            $line = substr_replace($line, str_pad('25', 2, ' ', STR_PAD_LEFT), 36, 2);
            $line = substr_replace($line, str_pad((string) $player['pid'], 6, '0', STR_PAD_LEFT), 38, 6);
            $line = substr_replace($line, str_pad((string) $player['teamid'], 2, ' ', STR_PAD_LEFT), 44, 2);
            $line = substr_replace($line, str_pad('28', 4, ' ', STR_PAD_LEFT), 46, 4);
            $line = substr_replace($line, 'PG', 50, 2);
            $line = substr_replace($line, str_pad('2000', 4, ' ', STR_PAD_LEFT), 56, 4);
            $line = substr_replace($line, str_pad('100', 4, ' ', STR_PAD_LEFT), 108, 4);

            $lines[] = $line;
        }

        $path = $this->tempDir . '/IBL5.plr';
        file_put_contents($path, implode("\r\n", $lines));
        return $path;
    }

    protected function buildScoFile(): string
    {
        $path = $this->tempDir . '/IBL5.sco';
        file_put_contents($path, str_repeat("\0", 1000000));
        return $path;
    }

    protected function buildSeason(string $phase, int $endingYear): Season
    {
        $season = new Season($this->db);
        $season->phase = $phase;
        $season->beginningYear = $endingYear - 1;
        $season->endingYear = $endingYear;

        return $season;
    }

    protected function buildPipeline(
        Season $season,
        string $schPath,
        string $plrPath,
        string $scoPath,
    ): UpdaterService {
        $service = new UpdaterService();

        $commonRepo = new CommonMysqliRepository($this->db);
        $plrRepo = new PlrParserRepository($this->db);
        $plrService = new PlrParserService($plrRepo, $commonRepo, $season);

        $boxscoreProcessor = new BoxscoreProcessor($this->db, null, $season);
        $boxscoreRepo = new BoxscoreRepository($this->db);
        $boxscoreView = new BoxscoreView();

        $savedDcRepo = new SavedDepthChartRepository($this->db);
        $sharedRepo = new SharedRepository($this->db);

        $jsbRepo = new JsbImportRepository($this->db);
        $jsbResolver = new PlayerIdResolver($this->db);
        $jsbService = new JsbImportService($jsbRepo, $jsbResolver);

        $schResolver = $this->createStub(JsbSourceResolverInterface::class);
        $schResolver->method('getContents')->willReturnCallback(
            static function (string $ext) use ($schPath): ?string {
                if ($ext === 'sch' && is_file($schPath)) {
                    $data = file_get_contents($schPath);
                    return $data !== false ? $data : null;
                }
                return null;
            },
        );
        $scheduleUpdater = new \Updater\ScheduleUpdater($this->db, $season, null, $schResolver);
        $standingsUpdater = new \Updater\StandingsUpdater($this->db, $season);
        $powerRankingsUpdater = new \Updater\PowerRankingsUpdater($this->db, $season);

        // Step 0: ExtractFromBackupStep — SKIPPED (files pre-placed in temp dir)
        // Step 1: ImportLeagueConfigStep — SKIPPED (pre-seeded via seedLeagueConfig)

        $service->addStep(new Steps\ParsePlayerFileStep($plrService, $plrPath));

        $service->addStep(new Steps\CleanupPreseasonDataStep(
            $boxscoreRepo, $season, $this->db,
        ));

        $service->addStep(new Steps\UpdateScheduleStep($scheduleUpdater));
        $service->addStep(new Steps\UpdateStandingsStep($standingsUpdater));
        $service->addStep(new Steps\UpdatePowerRankingsStep($powerRankingsUpdater));

        $service->addStep(new Steps\ResetExtensionAttemptsStep($sharedRepo));

        $service->addStep(new Steps\ExtendDepthChartsStep(
            $savedDcRepo, $season->lastSimEndDate, $season->lastSimNumber,
        ));

        $service->addStep(new Steps\ProcessBoxscoresStep(
            $boxscoreProcessor, $boxscoreView, $scoPath,
        ));

        $service->addStep(new Steps\ProcessAllStarGamesStep(
            $boxscoreProcessor, $boxscoreRepo, $boxscoreView, $scoPath,
        ));

        $service->addStep(new Steps\ParseJsbFilesStep($jsbService, $this->tempDir, $season, 'IBL5'));

        $service->addStep(new Steps\EndOfSeasonImportStep(
            $jsbRepo, $jsbService, $season->endingYear, $this->tempDir, 'IBL5',
        ));

        $service->addStep(new Steps\SnapshotPlrStep(
            $plrService, $jsbRepo, $season->endingYear, $plrPath,
        ));

        $service->addStep(new Steps\RefreshIblHistStep($this->db));

        return $service;
    }

    /**
     * @return list<StepResult>
     */
    protected function runPipeline(UpdaterService $service): array
    {
        $level = ob_get_level();
        ob_start();
        try {
            $results = $service->run(
                static function (\Updater\Contracts\PipelineStepInterface $step): void {},
                static function (StepResult $result): void {},
            );
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        return $results;
    }

    /**
     * @param list<StepResult> $results
     */
    protected function assertZeroPipelineErrors(UpdaterService $service, array $results = []): void
    {
        if ($service->getErrorCount() > 0) {
            $failures = [];
            foreach ($results as $r) {
                if (!$r->success) {
                    $failures[] = "{$r->label}: {$r->errorMessage}";
                }
            }
            self::fail('Pipeline had ' . $service->getErrorCount() . " errors:\n" . implode("\n", $failures));
        }
    }

    /**
     * @param list<StepResult> $results
     */
    protected function findResultByLabel(array $results, string $label): ?StepResult
    {
        foreach ($results as $result) {
            if ($result->label === $label) {
                return $result;
            }
        }

        return null;
    }

    protected function countRows(string $table, string $where = '1=1'): int
    {
        $result = $this->db->query("SELECT COUNT(*) AS cnt FROM {$table} WHERE {$where}");
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row);

        return (int) $row['cnt'];
    }
}
