<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\RetFileParser;

class RetImporter
{
    private JsbImportRepositoryInterface $repository;

    public function __construct(JsbImportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function import(string $data, int $retirementYear): JsbImportResult
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

    public function importFile(string $filePath, int $retirementYear): JsbImportResult
    {
        return FileReader::readOrFail($filePath, 'RET', fn (string $data) => $this->import($data, $retirementYear));
    }
}
