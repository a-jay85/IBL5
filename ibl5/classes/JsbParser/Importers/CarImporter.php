<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\CarFileParser;
use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\PlayerIdResolver;

class CarImporter
{
    private JsbImportRepositoryInterface $repository;
    private PlayerIdResolver $resolver;

    public function __construct(JsbImportRepositoryInterface $repository, PlayerIdResolver $resolver)
    {
        $this->repository = $repository;
        $this->resolver = $resolver;
    }

    public function import(string $data, ?int $filterYear = null): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = CarFileParser::parse($data);
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

                $resolvedTeamId = $this->repository->resolveTeamIdByName($histData['team']);

                $pid = $this->resolver->resolve($histData['name'], $histData['team'], $histData['year'], $resolvedTeamId);
                if ($pid === null) {
                    $result->addSkipped();
                    continue;
                }

                $result->addInserted();
            }
        }

        return $result;
    }

    public function importFile(string $filePath, ?int $filterYear = null): JsbImportResult
    {
        return FileReader::readOrFail($filePath, 'CAR', fn (string $data) => $this->import($data, $filterYear));
    }
}
