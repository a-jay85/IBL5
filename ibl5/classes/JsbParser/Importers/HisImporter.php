<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\HisFileParser;
use JsbParser\JsbImportResult;

class HisImporter
{
    private JsbImportRepositoryInterface $repository;

    public function __construct(JsbImportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function import(string $data, ?string $sourceLabel = null): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = HisFileParser::parse($data);
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
                    $result->recordUpsert($affected);
                } catch (\RuntimeException $e) {
                    $result->addError('History upsert failed for ' . $team['name'] . ' (' . $season['year'] . '): ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    public function importFile(string $filePath, ?string $sourceLabel = null): JsbImportResult
    {
        return FileReader::readOrFail($filePath, 'HIS', fn (string $data) => $this->import($data, $sourceLabel));
    }
}
