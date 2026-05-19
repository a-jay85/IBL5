<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\AswFileParser;
use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;

class AswImporter
{
    private JsbImportRepositoryInterface $repository;

    public function __construct(JsbImportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function import(string $data, int $seasonYear): JsbImportResult
    {
        $result = new JsbImportResult();

        try {
            $parsed = AswFileParser::parse($data);
        } catch (\RuntimeException $e) {
            $result->addError('ASW parse failed: ' . $e->getMessage());
            return $result;
        }

        /** @var array<string, list<int>> $rosters */
        $rosters = $parsed['rosters'];
        foreach ($rosters as $eventType => $playerIds) {
            foreach ($playerIds as $slot => $pid) {
                $playerName = $this->repository->getPlayerName($pid);

                try {
                    $affected = $this->repository->upsertAllStarRoster([
                        'season_year' => $seasonYear,
                        'event_type' => $eventType,
                        'roster_slot' => $slot + 1,
                        'pid' => $pid,
                        'player_name' => $playerName,
                    ]);
                    $result->recordUpsert($affected);
                } catch (\RuntimeException $e) {
                    $result->addError('All-Star roster upsert failed: ' . $e->getMessage());
                }
            }
        }

        $this->importContestScores($parsed['scores']['dunk_round1'], 'dunk_contest', 1, $seasonYear, $rosters['dunk_contest'], $result);
        $this->importContestScores($parsed['scores']['dunk_finals'], 'dunk_contest', 3, $seasonYear, $rosters['dunk_contest'], $result);
        $this->importContestScores($parsed['scores']['three_pt_round1'], 'three_point', 1, $seasonYear, $rosters['three_point'], $result);
        $this->importContestScores($parsed['scores']['three_pt_semis'], 'three_point', 2, $seasonYear, $rosters['three_point'], $result);
        $this->importContestScores($parsed['scores']['three_pt_finals'], 'three_point', 3, $seasonYear, $rosters['three_point'], $result);

        return $result;
    }

    public function importFile(string $filePath, int $seasonYear): JsbImportResult
    {
        return FileReader::readOrFail($filePath, 'ASW', fn (string $data) => $this->import($data, $seasonYear));
    }

    /**
     * @param list<int> $scores
     * @param list<int> $participants
     */
    private function importContestScores(
        array $scores,
        string $contestType,
        int $round,
        int $seasonYear,
        array $participants,
        JsbImportResult $result,
    ): void {
        foreach ($scores as $slot => $score) {
            $pid = $participants[$slot] ?? null;

            try {
                $affected = $this->repository->upsertAllStarScore([
                    'season_year' => $seasonYear,
                    'contest_type' => $contestType,
                    'round' => $round,
                    'participant_slot' => $slot + 1,
                    'pid' => $pid,
                    'score' => $score,
                ]);
                $result->recordUpsert($affected);
            } catch (\RuntimeException $e) {
                $result->addError('All-Star score upsert failed: ' . $e->getMessage());
            }
        }
    }
}
