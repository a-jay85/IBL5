<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\HofFileParser;
use JsbParser\JsbImportResult;

class HofImporter
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

    public function importFile(string $filePath): JsbImportResult
    {
        return FileReader::readOrFail($filePath, 'HOF', fn (string $data) => $this->import($data));
    }
}
