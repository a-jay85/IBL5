<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Contracts\JsbImportServiceInterface;
use JsbParser\Importers\AwaImporter;
use JsbParser\Importers\CarImporter;
use JsbParser\Importers\HisImporter;
use JsbParser\Importers\TrnImporter;
use PlrParser\PlrOrdinalMap;

/**
 * Orchestrator service for JSB file parsing and database import.
 *
 * Coordinates parsing of .car, .trn, .his, and .asw files, resolves player/team IDs,
 * and stores results in the database. Season stats flow from `ibl_box_scores` via the ibl_hist VIEW.
 */
class JsbImportService implements JsbImportServiceInterface
{
    private JsbImportRepositoryInterface $repository;
    private AwaImporter $awa;
    private CarImporter $car;
    private TrnImporter $trn;
    private HisImporter $his;

    public function __construct(JsbImportRepositoryInterface $repository, PlayerIdResolver $resolver)
    {
        $this->repository = $repository;
        $this->awa = new AwaImporter($repository);
        $this->car = new CarImporter($repository, $resolver);
        $this->trn = new TrnImporter($repository);
        $this->his = new HisImporter($repository);
    }

    /**
     * @see JsbImportServiceInterface::processCarData()
     */
    public function processCarData(string $data, ?int $filterYear = null): JsbImportResult
    {
        return $this->car->import($data, $filterYear);
    }

    /**
     * @see JsbImportServiceInterface::processCarFile()
     */
    public function processCarFile(string $filePath, ?int $filterYear = null): JsbImportResult
    {
        return $this->car->importFile($filePath, $filterYear);
    }

    /**
     * @see JsbImportServiceInterface::processTrnData()
     */
    public function processTrnData(string $data, ?string $sourceLabel = null): JsbImportResult
    {
        return $this->trn->import($data, $sourceLabel);
    }

    /**
     * @see JsbImportServiceInterface::processTrnFile()
     */
    public function processTrnFile(string $filePath, ?string $sourceLabel = null): JsbImportResult
    {
        return $this->trn->importFile($filePath, $sourceLabel);
    }

    /**
     * @see JsbImportServiceInterface::processHisData()
     */
    public function processHisData(string $data, ?string $sourceLabel = null): JsbImportResult
    {
        return $this->his->import($data, $sourceLabel);
    }

    /**
     * @see JsbImportServiceInterface::processHisFile()
     */
    public function processHisFile(string $filePath, ?string $sourceLabel = null): JsbImportResult
    {
        return $this->his->importFile($filePath, $sourceLabel);
    }

    /**
     * @see JsbImportServiceInterface::processAswData()
     */
    public function processAswData(string $data, int $seasonYear): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = AswFileParser::parse($data);
        } catch (\RuntimeException $e) {
            $result->addError('ASW parse failed: ' . $e->getMessage());
            return $result;
        }

        // Import rosters
        /** @var array<string, list<int>> $rosters */
        $rosters = $parsed['rosters'];
        foreach ($rosters as $eventType => $playerIds) {
            foreach ($playerIds as $slot => $pid) {
                $playerName = $this->repository->getPlayerName($pid);

                try {
                    $affected = $this->repository->upsertAllStarRoster([
                        'season_year' => $seasonYear,
                        'event_type' => $eventType,
                        'roster_slot' => $slot + 1,
                        'pid' => $pid,
                        'player_name' => $playerName,
                    ]);
                    $result->recordUpsert($affected);
                } catch (\RuntimeException $e) {
                    $result->addError('All-Star roster upsert failed: ' . $e->getMessage());
                }
            }
        }

        // Import dunk contest scores
        $this->importContestScores(
            $parsed['scores']['dunk_round1'],
            'dunk_contest',
            1,
            $seasonYear,
            $rosters['dunk_contest'],
            $result
        );
        $this->importContestScores(
            $parsed['scores']['dunk_finals'],
            'dunk_contest',
            3, // finals
            $seasonYear,
            $rosters['dunk_contest'],
            $result
        );

        // Import 3-point shootout scores
        $this->importContestScores(
            $parsed['scores']['three_pt_round1'],
            'three_point',
            1,
            $seasonYear,
            $rosters['three_point'],
            $result
        );
        $this->importContestScores(
            $parsed['scores']['three_pt_semis'],
            'three_point',
            2, // semifinals
            $seasonYear,
            $rosters['three_point'],
            $result
        );
        $this->importContestScores(
            $parsed['scores']['three_pt_finals'],
            'three_point',
            3, // finals
            $seasonYear,
            $rosters['three_point'],
            $result
        );

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processAswFile()
     */
    public function processAswFile(string $filePath, int $seasonYear): JsbImportResult
    {
        return $this->loadFileAndProcess($filePath, fn (string $c) => $this->processAswData($c, $seasonYear), 'ASW');
    }

    /**
     * @see JsbImportServiceInterface::processAwaData()
     */
    public function processAwaData(string $awaData, string $carData, ?int $filterYear = null): JsbImportResult
    {
        return $this->awa->import($awaData, $carData, $filterYear);
    }

    /**
     * @see JsbImportServiceInterface::processAwaFile()
     */
    public function processAwaFile(string $awaPath, string $carPath, ?int $filterYear = null): JsbImportResult
    {
        return $this->awa->importFiles($awaPath, $carPath, $filterYear);
    }

    /**
     * @see JsbImportServiceInterface::processRcbData()
     */
    public function processRcbData(string $data, int $seasonYear, ?string $sourceLabel = null, bool $includeAlltime = true): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = RcbFileParser::parse($data);
        } catch (\RuntimeException $e) {
            $result->addError('RCB parse failed: ' . $e->getMessage());
            return $result;
        }

        if ($includeAlltime) {
            if ($parsed['alltime'] === []) {
                $result->addMessage('RCB alltime section empty — skipping replace to avoid wiping authoritative state');
            } else {
                $alltimeRecords = array_map(
                    static fn (array $record): array => [
                        'scope' => $record['scope'],
                        'teamid' => $record['teamid'],
                        'record_type' => $record['record_type'],
                        'stat_category' => $record['stat_category'],
                        'ranking' => $record['ranking'],
                        'player_name' => $record['player_name'],
                        'car_block_id' => $record['car_block_id'],
                        'pid' => null,
                        'stat_value' => $record['stat_value'],
                        'stat_raw' => $record['stat_raw'],
                        'team_of_record' => $record['team_of_record'],
                        'season_year' => $record['season_year'],
                        'career_total' => $record['career_total'],
                        'source_file' => $sourceLabel,
                    ],
                    $parsed['alltime']
                );
                try {
                    $inserted = $this->repository->replaceRcbAlltimeRecords($alltimeRecords);
                    $result->addInserted($inserted);
                } catch (\RuntimeException $e) {
                    $result->addError('RCB alltime replace failed: ' . $e->getMessage());
                }
            }
        }

        if ($parsed['currentSeason'] === []) {
            $result->addMessage('RCB season section empty — skipping replace');
        } else {
            $seasonRecords = array_map(
                static fn (array $record): array => [
                    'season_year' => $seasonYear,
                    'scope' => $record['scope'],
                    'teamid' => $record['teamid'],
                    'context' => $record['context'],
                    'stat_category' => $record['stat_category'],
                    'ranking' => $record['ranking'],
                    'player_name' => $record['player_name'],
                    'player_position' => $record['player_position'],
                    'car_block_id' => $record['car_block_id'],
                    'pid' => null,
                    'stat_value' => $record['stat_value'],
                    'record_season_year' => $record['season_year'],
                    'source_file' => $sourceLabel,
                ],
                $parsed['currentSeason']
            );
            try {
                $inserted = $this->repository->replaceRcbSeasonRecords($seasonYear, $seasonRecords);
                $result->addInserted($inserted);
            } catch (\RuntimeException $e) {
                $result->addError('RCB season replace failed: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processRcbFile()
     */
    public function processRcbFile(string $filePath, int $seasonYear, ?string $sourceLabel = null, bool $includeAlltime = true): JsbImportResult
    {
        return $this->loadFileAndProcess($filePath, fn (string $c) => $this->processRcbData($c, $seasonYear, $sourceLabel, $includeAlltime), 'RCB');
    }

    /**
     * Import contest scores for a specific round.
     *
     * @param list<int> $scores Score values
     * @param string $contestType 'dunk_contest' or 'three_point'
     * @param int $round Round number (1=round1, 2=semis, 3=finals)
     * @param int $seasonYear Season year
     * @param list<int> $participants Participant PIDs from roster section
     */
    private function importContestScores(
        array $scores,
        string $contestType,
        int $round,
        int $seasonYear,
        array $participants,
        JsbImportResult $result,
    ): void {
        foreach ($scores as $slot => $score) {
            // Try to match score slot to participant
            $pid = $participants[$slot] ?? null;

            try {
                $affected = $this->repository->upsertAllStarScore([
                    'season_year' => $seasonYear,
                    'contest_type' => $contestType,
                    'round' => $round,
                    'participant_slot' => $slot + 1,
                    'pid' => $pid,
                    'score' => $score,
                ]);
                $result->recordUpsert($affected);
            } catch (\RuntimeException $e) {
                $result->addError('All-Star score upsert failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @see JsbImportServiceInterface::processPlbData()
     */
    public function processPlbData(
        string $data,
        PlrOrdinalMap $map,
        int $seasonYear,
        int $simNumber,
        string $sourceArchive,
    ): JsbImportResult {
        $result = new JsbImportResult();

        try {
            $parsed = PlbFileParser::parse($data);
        } catch (\RuntimeException $e) {
            $result->addError('PLB parse failed: ' . $e->getMessage());
            return $result;
        }

        foreach ($parsed as $lineIndex => $slots) {
            // .plb line index is 0-based; teamid = lineIndex + 1 (1-based team ID)
            $teamid = $lineIndex + 1;

            // Skip special teams (teamid > 28 = rookies, all-stars, etc.)
            if ($teamid > 28) {
                continue;
            }

            foreach ($slots as $slot) {
                // Skip empty slots (zero minutes)
                if ($slot['dc_minutes'] === 0) {
                    $result->addSkipped();
                    continue;
                }

                // Resolve player identity from ordinal map
                $player = $map->getSlotPlayer($teamid, $slot['slot_index']);
                $pid = $player !== null ? $player['pid'] : null;
                $playerName = $player !== null ? $player['name'] : null;

                try {
                    $affected = $this->repository->upsertPlbSnapshot([
                        'season_year' => $seasonYear,
                        'sim_number' => $simNumber,
                        'source_archive' => $sourceArchive,
                        'teamid' => $teamid,
                        'slot_index' => $slot['slot_index'],
                        'pid' => $pid,
                        'player_name' => $playerName,
                        'dc_minutes' => $slot['dc_minutes'],
                        'dc_of' => $slot['dc_of'],
                        'dc_df' => $slot['dc_df'],
                        'dc_oi' => $slot['dc_oi'],
                        'dc_di' => $slot['dc_di'],
                        'dc_bh' => $slot['dc_bh'],
                    ]);
                    $result->recordUpsert($affected);
                } catch (\RuntimeException $e) {
                    $result->addError('PLB upsert failed for teamid=' . $teamid . ' slot=' . $slot['slot_index'] . ': ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processPlbFile()
     */
    public function processPlbFile(
        string $filePath,
        PlrOrdinalMap $map,
        int $seasonYear,
        int $simNumber,
        string $sourceArchive,
    ): JsbImportResult {
        return $this->loadFileAndProcess($filePath, fn (string $c) => $this->processPlbData($c, $map, $seasonYear, $simNumber, $sourceArchive), 'PLB');
    }

    /**
     * @see JsbImportServiceInterface::processDraData()
     */
    public function processDraData(string $data): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = DraFileParser::parse($data);
        } catch (\RuntimeException $e) {
            $result->addError('DRA parse failed: ' . $e->getMessage());
            return $result;
        }

        foreach ($parsed as $draft) {
            foreach ($draft['picks'] as $pick) {
                try {
                    $affected = $this->repository->upsertDraftResult([
                        'draft_year' => $draft['draft_year'],
                        'round' => $pick['round'],
                        'pick' => $pick['pick'],
                        'team_name' => $pick['team_name'],
                        'pos' => $pick['pos'],
                        'player_name' => $pick['player_name'],
                        'pid' => null,
                    ]);
                    $result->recordUpsert($affected);
                } catch (\RuntimeException $e) {
                    $result->addError('Draft upsert failed for ' . $pick['player_name'] . ': ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processDraFile()
     */
    public function processDraFile(string $filePath): JsbImportResult
    {
        return $this->loadFileAndProcess($filePath, fn (string $c) => $this->processDraData($c), 'DRA');
    }

    /**
     * @see JsbImportServiceInterface::processRetData()
     */
    public function processRetData(string $data, int $retirementYear): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = RetFileParser::parse($data);
        } catch (\RuntimeException $e) {
            $result->addError('RET parse failed: ' . $e->getMessage());
            return $result;
        }

        foreach ($parsed as $entry) {
            $pid = $this->repository->getPlayerName($entry['jsb_pid']) !== null
                ? $entry['jsb_pid']
                : null;

            try {
                $affected = $this->repository->upsertRetiredPlayer([
                    'jsb_pid' => $entry['jsb_pid'],
                    'retirement_year' => $retirementYear,
                    'player_name' => $entry['player_name'],
                    'pid' => $pid,
                ]);
                $result->recordUpsert($affected);
            } catch (\RuntimeException $e) {
                $result->addError('Retired player upsert failed for ' . $entry['player_name'] . ': ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processRetFile()
     */
    public function processRetFile(string $filePath, int $retirementYear): JsbImportResult
    {
        return $this->loadFileAndProcess($filePath, fn (string $c) => $this->processRetData($c, $retirementYear), 'RET');
    }

    /**
     * @see JsbImportServiceInterface::processHofData()
     */
    public function processHofData(string $data): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = HofFileParser::parse($data);
        } catch (\RuntimeException $e) {
            $result->addError('HOF parse failed: ' . $e->getMessage());
            return $result;
        }

        foreach ($parsed as $entry) {
            $pid = $this->repository->getPlayerName($entry['jsb_pid']) !== null
                ? $entry['jsb_pid']
                : null;

            try {
                $affected = $this->repository->upsertHofInductee([
                    'jsb_pid' => $entry['jsb_pid'],
                    'player_name' => $entry['player_name'],
                    'pos' => $entry['pos'],
                    'induction_year' => $entry['induction_year'],
                    'pid' => $pid,
                ]);
                $result->recordUpsert($affected);
            } catch (\RuntimeException $e) {
                $result->addError('HoF upsert failed for ' . $entry['player_name'] . ': ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processHofFile()
     */
    public function processHofFile(string $filePath): JsbImportResult
    {
        return $this->loadFileAndProcess($filePath, fn (string $c) => $this->processHofData($c), 'HOF');
    }

    /**
     * @param callable(string): JsbImportResult $processor
     */
    private function loadFileAndProcess(string $filePath, callable $processor, string $label): JsbImportResult
    {
        if (!file_exists($filePath)) {
            $result = new JsbImportResult();
            $result->addError($label . ' file not found: ' . $filePath);
            return $result;
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            $result = new JsbImportResult();
            $result->addError('Failed to read ' . $label . ' file: ' . $filePath);
            return $result;
        }

        return $processor($data);
    }
}
