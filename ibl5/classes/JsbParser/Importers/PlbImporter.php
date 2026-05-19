<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\PlbFileParser;
use PlrParser\PlrOrdinalMap;

class PlbImporter
{
    private JsbImportRepositoryInterface $repository;

    public function __construct(JsbImportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function import(
        string $data,
        PlrOrdinalMap $map,
        int $seasonYear,
        int $simNumber,
        string $sourceArchive,
    ): JsbImportResult {
        $result = new JsbImportResult();

        try {
            $parsed = PlbFileParser::parse($data);
        } catch (\RuntimeException $e) {
            $result->addError('PLB parse failed: ' . $e->getMessage());
            return $result;
        }

        foreach ($parsed as $lineIndex => $slots) {
            $teamid = $lineIndex + 1;

            if ($teamid > 28) {
                continue;
            }

            foreach ($slots as $slot) {
                if ($slot['dc_minutes'] === 0) {
                    $result->addSkipped();
                    continue;
                }

                $player = $map->getSlotPlayer($teamid, $slot['slot_index']);
                $pid = $player !== null ? $player['pid'] : null;
                $playerName = $player !== null ? $player['name'] : null;

                try {
                    $affected = $this->repository->upsertPlbSnapshot([
                        'season_year' => $seasonYear,
                        'sim_number' => $simNumber,
                        'source_archive' => $sourceArchive,
                        'teamid' => $teamid,
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
                    $result->recordUpsert($affected);
                } catch (\RuntimeException $e) {
                    $result->addError('PLB upsert failed for teamid=' . $teamid . ' slot=' . $slot['slot_index'] . ': ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    public function importFile(
        string $filePath,
        PlrOrdinalMap $map,
        int $seasonYear,
        int $simNumber,
        string $sourceArchive,
    ): JsbImportResult {
        return FileReader::readOrFail($filePath, 'PLB', fn (string $data) => $this->import($data, $map, $seasonYear, $simNumber, $sourceArchive));
    }
}
