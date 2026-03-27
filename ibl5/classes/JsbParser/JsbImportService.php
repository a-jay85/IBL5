<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Contracts\JsbImportServiceInterface;
use Season\Season;

/**
 * Orchestrator service for JSB file parsing and database import.
 *
 * Coordinates parsing of .car, .trn, .his, and .asw files, resolves player/team IDs,
 * and stores results in the database. Handles stat conversion for ibl_hist compatibility.
 */
class JsbImportService implements JsbImportServiceInterface
{
    private JsbImportRepositoryInterface $repository;
    private PlayerIdResolver $resolver;

    /** @var int Auto-incrementing trade group ID for grouping trade items */
    private int $nextTradeGroupId = 1;

    /**
     * MySQL affected_rows for INSERT ... ON DUPLICATE KEY UPDATE:
     * 1 = new row inserted, 2 = existing row updated, 0 = no change.
     */
    private const AFFECTED_ROWS_INSERTED = 1;

    public function __construct(JsbImportRepositoryInterface $repository, PlayerIdResolver $resolver)
    {
        $this->repository = $repository;
        $this->resolver = $resolver;
    }

    /**
     * @see JsbImportServiceInterface::processCurrentSeason()
     */
    public function processCurrentSeason(string $basePath, Season $season, string $filePrefix = 'IBL5'): JsbImportResult
    {
        $result = new JsbImportResult();
        $seasonYear = $season->beginningYear;

        // Process .trn first (trade data helps with player ID resolution)
        $trnPath = $basePath . '/' . $filePrefix . '.trn';
        if (file_exists($trnPath)) {
            $trnResult = $this->processTrnFile($trnPath, 'current-season');
            $result->merge($trnResult);
            $result->addMessage('TRN: ' . $trnResult->summary());
        }

        // Process .car (uses trade data for mid-season splits)
        $carPath = $basePath . '/' . $filePrefix . '.car';
        if (file_exists($carPath)) {
            $carResult = $this->processCarFile($carPath, $seasonYear);
            $result->merge($carResult);
            $result->addMessage('CAR: ' . $carResult->summary());
        }

        // Process .his
        $hisPath = $basePath . '/' . $filePrefix . '.his';
        if (file_exists($hisPath)) {
            $hisResult = $this->processHisFile($hisPath, 'current-season');
            $result->merge($hisResult);
            $result->addMessage('HIS: ' . $hisResult->summary());
        }

        // Process .asw
        $aswPath = $basePath . '/' . $filePrefix . '.asw';
        if (file_exists($aswPath)) {
            $aswResult = $this->processAswFile($aswPath, $seasonYear);
            $result->merge($aswResult);
            $result->addMessage('ASW: ' . $aswResult->summary());
        }

        // Process .rcb (Record Book)
        $rcbPath = $basePath . '/' . $filePrefix . '.rcb';
        if (file_exists($rcbPath)) {
            $rcbResult = $this->processRcbFile($rcbPath, $seasonYear, 'current-season');
            $result->merge($rcbResult);
            $result->addMessage('RCB: ' . $rcbResult->summary());
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processCarFile()
     */
    public function processCarFile(string $filePath, ?int $filterYear = null): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = CarFileParser::parseFile($filePath);
        } catch (\RuntimeException $e) {
            $result->addError('CAR parse failed: ' . $e->getMessage());
            return $result;
        }

        foreach ($parsed['players'] as $player) {
            foreach ($player['seasons'] as $season) {
                if ($filterYear !== null && $season['year'] !== $filterYear) {
                    continue;
                }

                $histData = CarFileParser::convertToHistFormat($season);

                // Resolve team ID
                $resolvedTeamId = $this->repository->resolveTeamIdByName($histData['team']);
                $teamId = $resolvedTeamId ?? 0;

                // Resolve player ID (pass resolvedTeamId for tid-based ibl_plr lookup; null skips Strategy 2)
                $pid = $this->resolver->resolve($histData['name'], $histData['team'], $histData['year'], $resolvedTeamId);
                if ($pid === null) {
                    $result->addSkipped();
                    continue;
                }

                try {
                    $affected = $this->repository->upsertHistRecord([
                        'pid' => $pid,
                        'name' => $histData['name'],
                        'year' => $histData['year'],
                        'team' => $histData['team'],
                        'teamid' => $teamId,
                        'games' => $histData['games'],
                        'minutes' => $histData['minutes'],
                        'fgm' => $histData['fgm'],
                        'fga' => $histData['fga'],
                        'ftm' => $histData['ftm'],
                        'fta' => $histData['fta'],
                        'tgm' => $histData['tgm'],
                        'tga' => $histData['tga'],
                        'orb' => $histData['orb'],
                        'reb' => $histData['reb'],
                        'ast' => $histData['ast'],
                        'stl' => $histData['stl'],
                        'blk' => $histData['blk'],
                        'tvr' => $histData['tvr'],
                        'pf' => $histData['pf'],
                        'pts' => $histData['pts'],
                    ]);
                    $this->recordUpsertResult($affected, $result);
                } catch (\RuntimeException $e) {
                    $result->addError('Hist upsert failed for ' . $histData['name'] . ': ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processTrnFile()
     */
    public function processTrnFile(string $filePath, ?string $sourceLabel = null): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = TrnFileParser::parseFile($filePath);
        } catch (\RuntimeException $e) {
            $result->addError('TRN parse failed: ' . $e->getMessage());
            return $result;
        }

        // Get the max existing trade_group_id to avoid collisions
        $this->initTradeGroupId();

        foreach ($parsed['transactions'] as $transaction) {
            $type = $transaction['type'];

            switch ($type) {
                case TrnFileParser::TYPE_INJURY:
                    $this->importInjuryTransaction($transaction, $sourceLabel, $result);
                    break;

                case TrnFileParser::TYPE_TRADE:
                    $this->importTradeTransaction($transaction, $sourceLabel, $result);
                    break;

                case TrnFileParser::TYPE_WAIVER_CLAIM:
                case TrnFileParser::TYPE_WAIVER_RELEASE:
                    $this->importWaiverTransaction($transaction, $sourceLabel, $result);
                    break;
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processHisFile()
     */
    public function processHisFile(string $filePath, ?string $sourceLabel = null): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = HisFileParser::parseFile($filePath);
        } catch (\RuntimeException $e) {
            $result->addError('HIS parse failed: ' . $e->getMessage());
            return $result;
        }

        foreach ($parsed as $season) {
            foreach ($season['teams'] as $team) {
                $teamId = $this->repository->resolveTeamIdByName($team['name']);

                try {
                    $affected = $this->repository->upsertHistoryRecord([
                        'season_year' => $season['year'],
                        'team_name' => $team['name'],
                        'teamid' => $teamId,
                        'wins' => $team['wins'],
                        'losses' => $team['losses'],
                        'made_playoffs' => $team['made_playoffs'],
                        'playoff_result' => $team['playoff_result'] !== '' ? $team['playoff_result'] : null,
                        'playoff_round_reached' => $team['playoff_round_reached'] !== '' ? $team['playoff_round_reached'] : null,
                        'won_championship' => $team['won_championship'],
                        'source_file' => $sourceLabel,
                    ]);
                    $this->recordUpsertResult($affected, $result);
                } catch (\RuntimeException $e) {
                    $result->addError('History upsert failed for ' . $team['name'] . ' (' . $season['year'] . '): ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processAswFile()
     */
    public function processAswFile(string $filePath, int $seasonYear): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = AswFileParser::parseFile($filePath);
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
                    $this->recordUpsertResult($affected, $result);
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

    /** @var list<string> Rank suffixes for award names */
    private const RANK_SUFFIXES = ['(1st)', '(2nd)', '(3rd)', '(4th)', '(5th)'];

    /**
     * @see JsbImportServiceInterface::processAwaFile()
     */
    public function processAwaFile(string $awaPath, string $carPath, ?int $filterYear = null): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $awaParsed = AwaFileParser::parseFile($awaPath);
        } catch (\RuntimeException $e) {
            $result->addError('AWA parse failed: ' . $e->getMessage());
            return $result;
        }

        // Build PID → name map from .car file
        try {
            $carParsed = CarFileParser::parseFile($carPath);
        } catch (\RuntimeException $e) {
            $result->addError('CAR parse failed (for AWA name resolution): ' . $e->getMessage());
            return $result;
        }

        /** @var array<int, string> $pidNameMap */
        $pidNameMap = [];
        foreach ($carParsed['players'] as $player) {
            // .car stores names in CP1252; convert to UTF-8 for DB storage
            $name = mb_convert_encoding($player['name'], 'UTF-8', 'Windows-1252');
            $pidNameMap[$player['block_index']] = $name;
        }

        foreach ($awaParsed['seasons'] as $season) {
            if ($filterYear !== null && $season['year'] !== $filterYear) {
                continue;
            }

            foreach ($season['stat_leaders'] as $category => $leaders) {
                foreach ($leaders as $leader) {
                    $playerName = $pidNameMap[$leader['pid']] ?? null;
                    if ($playerName === null) {
                        $result->addSkipped();
                        continue;
                    }

                    $rankIndex = $leader['rank'] - 1;
                    if ($rankIndex < 0 || $rankIndex >= 5) {
                        $result->addSkipped();
                        continue;
                    }

                    $awardName = $category . ' ' . self::RANK_SUFFIXES[$rankIndex];

                    try {
                        $affected = $this->repository->upsertAward($season['year'], $awardName, $playerName);
                        $this->recordUpsertResult($affected, $result);
                    } catch (\RuntimeException $e) {
                        $result->addError('Award upsert failed for ' . $playerName . ': ' . $e->getMessage());
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processRcbFile()
     */
    public function processRcbFile(string $filePath, int $seasonYear, ?string $sourceLabel = null): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = RcbFileParser::parseFile($filePath);
        } catch (\RuntimeException $e) {
            $result->addError('RCB parse failed: ' . $e->getMessage());
            return $result;
        }

        // Import all-time records
        foreach ($parsed['alltime'] as $record) {
            try {
                $affected = $this->repository->upsertRcbAlltimeRecord([
                    'scope' => $record['scope'],
                    'team_id' => $record['team_id'],
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
                ]);
                $this->recordUpsertResult($affected, $result);
            } catch (\RuntimeException $e) {
                $result->addError('RCB alltime upsert failed for ' . $record['player_name'] . ': ' . $e->getMessage());
            }
        }

        // Import current season records
        foreach ($parsed['currentSeason'] as $record) {
            try {
                $affected = $this->repository->upsertRcbSeasonRecord([
                    'season_year' => $seasonYear,
                    'scope' => $record['scope'],
                    'team_id' => $record['team_id'],
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
                ]);
                $this->recordUpsertResult($affected, $result);
            } catch (\RuntimeException $e) {
                $result->addError('RCB season upsert failed for ' . $record['player_name'] . ': ' . $e->getMessage());
            }
        }

        return $result;
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
                $this->recordUpsertResult($affected, $result);
            } catch (\RuntimeException $e) {
                $result->addError('All-Star score upsert failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Import an injury transaction.
     *
     * @param array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null} $transaction
     */
    private function importInjuryTransaction(array $transaction, ?string $sourceLabel, JsbImportResult $result): void
    {
        $pid = $transaction['pid'];
        $playerName = null;
        if ($pid !== null) {
            $playerName = $this->repository->getPlayerName($pid);
        }

        try {
            $affected = $this->repository->upsertTransaction([
                'season_year' => $transaction['year'],
                'transaction_month' => $transaction['month'],
                'transaction_day' => $transaction['day'],
                'transaction_type' => TrnFileParser::TYPE_INJURY,
                'pid' => $pid ?? 0,
                'player_name' => $playerName,
                'from_teamid' => $transaction['team_id'] ?? 0,
                'to_teamid' => 0,
                'injury_games_missed' => $transaction['games_missed'],
                'injury_description' => $transaction['injury_description'],
                'trade_group_id' => null,
                'is_draft_pick' => 0,
                'draft_pick_year' => null,
                'source_file' => $sourceLabel,
            ]);
            $this->recordUpsertResult($affected, $result);
        } catch (\RuntimeException $e) {
            $result->addError('Injury transaction upsert failed: ' . $e->getMessage());
        }
    }

    /**
     * Import a trade transaction (may contain multiple items).
     *
     * @param array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null} $transaction
     */
    private function importTradeTransaction(array $transaction, ?string $sourceLabel, JsbImportResult $result): void
    {
        $items = $transaction['trade_items'];
        if ($items === null || $items === []) {
            // Separator record — skip
            return;
        }

        $tradeGroupId = $this->nextTradeGroupId++;

        foreach ($items as $item) {
            $pid = $item['player_id'];
            $playerName = null;
            if ($pid !== null) {
                $playerName = $this->repository->getPlayerName($pid);
            }

            $isDraftPick = $item['marker'] === TrnFileParser::TRADE_MARKER_DRAFT_PICK ? 1 : 0;

            try {
                $affected = $this->repository->upsertTransaction([
                    'season_year' => $transaction['year'],
                    'transaction_month' => $transaction['month'],
                    'transaction_day' => $transaction['day'],
                    'transaction_type' => TrnFileParser::TYPE_TRADE,
                    'pid' => $pid ?? 0,
                    'player_name' => $playerName,
                    'from_teamid' => $item['from_team'],
                    'to_teamid' => $item['to_team'],
                    'injury_games_missed' => null,
                    'injury_description' => null,
                    'trade_group_id' => $tradeGroupId,
                    'is_draft_pick' => $isDraftPick,
                    'draft_pick_year' => $item['draft_year'],
                    'source_file' => $sourceLabel,
                ]);
                $this->recordUpsertResult($affected, $result);
            } catch (\RuntimeException $e) {
                $result->addError('Trade transaction upsert failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Import a waiver claim or release transaction.
     *
     * @param array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null} $transaction
     */
    private function importWaiverTransaction(array $transaction, ?string $sourceLabel, JsbImportResult $result): void
    {
        $pid = $transaction['pid'];
        $playerName = null;
        if ($pid !== null) {
            $playerName = $this->repository->getPlayerName($pid);
        }

        $isRelease = $transaction['type'] === TrnFileParser::TYPE_WAIVER_RELEASE;

        try {
            $affected = $this->repository->upsertTransaction([
                'season_year' => $transaction['year'],
                'transaction_month' => $transaction['month'],
                'transaction_day' => $transaction['day'],
                'transaction_type' => $transaction['type'],
                'pid' => $pid ?? 0,
                'player_name' => $playerName,
                'from_teamid' => $isRelease ? ($transaction['team_id'] ?? 0) : 0,
                'to_teamid' => $isRelease ? 0 : ($transaction['team_id'] ?? 0),
                'injury_games_missed' => null,
                'injury_description' => null,
                'trade_group_id' => null,
                'is_draft_pick' => 0,
                'draft_pick_year' => null,
                'source_file' => $sourceLabel,
            ]);
            $this->recordUpsertResult($affected, $result);
        } catch (\RuntimeException $e) {
            $result->addError('Waiver transaction upsert failed: ' . $e->getMessage());
        }
    }

    /**
     * Record an upsert result based on MySQL affected_rows.
     *
     * @param int $affectedRows 1=inserted, 2=updated, 0=unchanged
     */
    private function recordUpsertResult(int $affectedRows, JsbImportResult $result): void
    {
        if ($affectedRows === self::AFFECTED_ROWS_INSERTED) {
            $result->addInserted();
        } else {
            $result->addUpdated();
        }
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
        $result = new JsbImportResult();

        try {
            $parsed = PlbFileParser::parseFile($filePath);
        } catch (\RuntimeException $e) {
            $result->addError('PLB parse failed: ' . $e->getMessage());
            return $result;
        }

        foreach ($parsed as $lineIndex => $slots) {
            // .plb line index is 0-based; tid = lineIndex + 1 (1-based team ID)
            $tid = $lineIndex + 1;

            // Skip special teams (tid > 28 = rookies, all-stars, etc.)
            if ($tid > 28) {
                continue;
            }

            foreach ($slots as $slot) {
                // Skip empty slots (zero minutes)
                if ($slot['dc_minutes'] === 0) {
                    $result->addSkipped();
                    continue;
                }

                // Resolve player identity from ordinal map
                $player = $map->getSlotPlayer($tid, $slot['slot_index']);
                $pid = $player !== null ? $player['pid'] : null;
                $playerName = $player !== null ? $player['name'] : null;

                try {
                    $affected = $this->repository->upsertPlbSnapshot([
                        'season_year' => $seasonYear,
                        'sim_number' => $simNumber,
                        'source_archive' => $sourceArchive,
                        'tid' => $tid,
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
                    $this->recordUpsertResult($affected, $result);
                } catch (\RuntimeException $e) {
                    $result->addError('PLB upsert failed for tid=' . $tid . ' slot=' . $slot['slot_index'] . ': ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * Initialize trade group ID counter from existing data.
     */
    private function initTradeGroupId(): void
    {
        try {
            $row = $this->repository->fetchMaxTradeGroupId();
            $this->nextTradeGroupId = $row + 1;
        } catch (\RuntimeException) {
            $this->nextTradeGroupId = 1;
        }
    }

    /**
     * @see JsbImportServiceInterface::processDraFile()
     */
    public function processDraFile(string $filePath): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = DraFileParser::parseFile($filePath);
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
                    $this->recordUpsertResult($affected, $result);
                } catch (\RuntimeException $e) {
                    $result->addError('Draft upsert failed for ' . $pick['player_name'] . ': ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processRetFile()
     */
    public function processRetFile(string $filePath, int $retirementYear): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = RetFileParser::parseFile($filePath);
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
                $this->recordUpsertResult($affected, $result);
            } catch (\RuntimeException $e) {
                $result->addError('Retired player upsert failed for ' . $entry['player_name'] . ': ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * @see JsbImportServiceInterface::processHofFile()
     */
    public function processHofFile(string $filePath): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = HofFileParser::parseFile($filePath);
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
                $this->recordUpsertResult($affected, $result);
            } catch (\RuntimeException $e) {
                $result->addError('HoF upsert failed for ' . $entry['player_name'] . ': ' . $e->getMessage());
            }
        }

        return $result;
    }
}
