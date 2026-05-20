<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\AwaFileParser;
use JsbParser\CarFileParser;
use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;

class AwaImporter
{
    /** @var list<string> */
    private const RANK_SUFFIXES = ['(1st)', '(2nd)', '(3rd)', '(4th)', '(5th)'];

    private JsbImportRepositoryInterface $repository;

    public function __construct(JsbImportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function import(string $awaData, string $carData, ?int $filterYear = null): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $awaParsed = AwaFileParser::parse($awaData);
        } catch (\RuntimeException $e) {
            $result->addError('AWA parse failed: ' . $e->getMessage());
            return $result;
        }

        try {
            $carParsed = CarFileParser::parse($carData);
        } catch (\RuntimeException $e) {
            $result->addError('CAR parse failed (for AWA name resolution): ' . $e->getMessage());
            return $result;
        }

        /** @var array<int, string> $pidNameMap */
        $pidNameMap = [];
        foreach ($carParsed['players'] as $player) {
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
                        $result->recordUpsert($affected);
                    } catch (\RuntimeException $e) {
                        $result->addError('Award upsert failed for ' . $playerName . ': ' . $e->getMessage());
                    }
                }
            }
        }

        return $result;
    }

    public function importFiles(string $awaPath, string $carPath, ?int $filterYear = null): JsbImportResult
    {
        if (!file_exists($awaPath) || !file_exists($carPath)) {
            $result = new JsbImportResult();
            $result->addError('AWA or CAR file not found');
            return $result;
        }

        $awaData = file_get_contents($awaPath);
        $carData = file_get_contents($carPath);
        if ($awaData === false || $carData === false) {
            $result = new JsbImportResult();
            $result->addError('Failed to read AWA or CAR file');
            return $result;
        }

        return $this->import($awaData, $carData, $filterYear);
    }
}
