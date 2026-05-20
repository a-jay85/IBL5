<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\DraFileParser;
use JsbParser\JsbImportResult;

class DraImporter
{
    private JsbImportRepositoryInterface $repository;

    public function __construct(JsbImportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function import(string $data): JsbImportResult
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

    public function importFile(string $filePath): JsbImportResult
    {
        return FileReader::readOrFail($filePath, 'DRA', fn (string $data) => $this->import($data));
    }
}
