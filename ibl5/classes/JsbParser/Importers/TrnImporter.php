<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\TrnFileParser;

class TrnImporter
{
    private JsbImportRepositoryInterface $repository;

    /** @var int Auto-incrementing trade group ID for grouping trade items */
    private int $nextTradeGroupId = 1;

    public function __construct(JsbImportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function import(string $data, ?string $sourceLabel = null): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = TrnFileParser::parse($data);
        } catch (\RuntimeException $e) {
            $result->addError('TRN parse failed: ' . $e->getMessage());
            return $result;
        }

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

    public function importFile(string $filePath, ?string $sourceLabel = null): JsbImportResult
    {
        if (!file_exists($filePath)) {
            $result = new JsbImportResult();
            $result->addError('TRN file not found: ' . $filePath);
            return $result;
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            $result = new JsbImportResult();
            $result->addError('Failed to read TRN file: ' . $filePath);
            return $result;
        }

        return $this->import($data, $sourceLabel);
    }

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
            $result->recordUpsert($affected);
        } catch (\RuntimeException $e) {
            $result->addError('Injury transaction upsert failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array{index: int, month: int, day: int, year: int, type: int, pid: int|null, team_id: int|null, games_missed: int|null, injury_description: string|null, trade_items: list<array{marker: int, from_team: int, to_team: int, player_id: int|null, draft_year: int|null}>|null} $transaction
     */
    private function importTradeTransaction(array $transaction, ?string $sourceLabel, JsbImportResult $result): void
    {
        $items = $transaction['trade_items'];
        if ($items === null || $items === []) {
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
                $result->recordUpsert($affected);
            } catch (\RuntimeException $e) {
                $result->addError('Trade transaction upsert failed: ' . $e->getMessage());
            }
        }
    }

    /**
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
            $result->recordUpsert($affected);
        } catch (\RuntimeException $e) {
            $result->addError('Waiver transaction upsert failed: ' . $e->getMessage());
        }
    }
}
