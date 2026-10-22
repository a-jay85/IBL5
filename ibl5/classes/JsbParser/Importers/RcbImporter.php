<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\RcbFileParser;

/**
 * @phpstan-type AlltimeEntry array{scope: string, teamid: int, record_type: string, stat_category: string, ranking: int, player_name: string, car_block_id: int, stat_value: float, stat_raw: int, team_of_record: int|null, season_year: int|null, career_total: int|null}
 * @phpstan-type SeasonEntry array{scope: string, teamid: int, context: string, stat_category: string, ranking: int, player_name: string, player_position: string, car_block_id: int, stat_value: int, season_year: int}
 * @phpstan-type AlltimeRow array{scope: string, teamid: int, record_type: string, stat_category: string, ranking: int, player_name: string, car_block_id: int, pid: int|null, stat_value: float, stat_raw: int, team_of_record: int|null, season_year: int|null, career_total: int|null, source_file: string|null}
 * @phpstan-type SeasonRow array{season_year: int, scope: string, teamid: int, context: string, stat_category: string, ranking: int, player_name: string, player_position: string|null, car_block_id: int, pid: int|null, stat_value: int, record_season_year: int, source_file: string|null}
 */
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
            $this->importAlltime($parsed['alltime'], $sourceLabel, $result);
        }

        $this->importSeason($parsed['currentSeason'], $seasonYear, $sourceLabel, $result);

        return $result;
    }

    public function importFile(string $filePath, int $seasonYear, ?string $sourceLabel = null, bool $includeAlltime = true): JsbImportResult
    {
        return FileReader::readOrFail($filePath, 'RCB', fn (string $data) => $this->import($data, $seasonYear, $sourceLabel, $includeAlltime));
    }

    /**
     * @param list<AlltimeEntry> $entries
     */
    private function importAlltime(array $entries, ?string $sourceLabel, JsbImportResult $result): void
    {
        if ($entries === []) {
            $result->addMessage('RCB alltime section empty — skipping replace to avoid wiping authoritative state');
            return;
        }

        $records = self::buildAlltimeRecords($entries, $sourceLabel);
        try {
            $inserted = $this->repository->replaceRcbAlltimeRecords($records);
            $result->addInserted($inserted);
        } catch (\RuntimeException $e) {
            $result->addError('RCB alltime replace failed: ' . $e->getMessage());
        }
    }

    /**
     * @param list<SeasonEntry> $entries
     */
    private function importSeason(array $entries, int $seasonYear, ?string $sourceLabel, JsbImportResult $result): void
    {
        if ($entries === []) {
            $result->addMessage('RCB season section empty — skipping replace');
            return;
        }

        $records = self::buildSeasonRecords($entries, $seasonYear, $sourceLabel);
        try {
            $inserted = $this->repository->replaceRcbSeasonRecords($seasonYear, $records);
            $result->addInserted($inserted);
        } catch (\RuntimeException $e) {
            $result->addError('RCB season replace failed: ' . $e->getMessage());
        }
    }

    /**
     * Keep the list<...> tags: without them PHPStan raises missingType.iterableValue.
     *
     * @param list<AlltimeEntry> $entries
     * @return list<AlltimeRow>
     */
    private static function buildAlltimeRecords(array $entries, ?string $sourceLabel): array
    {
        return array_map(
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
            $entries
        );
    }

    /**
     * @param list<SeasonEntry> $entries
     * @return list<SeasonRow>
     */
    private static function buildSeasonRecords(array $entries, int $seasonYear, ?string $sourceLabel): array
    {
        return array_map(
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
            $entries
        );
    }
}
