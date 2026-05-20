<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\RcbFileParser;

class RcbImporter
{
    private JsbImportRepositoryInterface $repository;

    public function __construct(JsbImportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function import(string $data, int $seasonYear, ?string $sourceLabel = null, bool $includeAlltime = true): JsbImportResult
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

    public function importFile(string $filePath, int $seasonYear, ?string $sourceLabel = null, bool $includeAlltime = true): JsbImportResult
    {
        return FileReader::readOrFail($filePath, 'RCB', fn (string $data) => $this->import($data, $seasonYear, $sourceLabel, $includeAlltime));
    }
}
