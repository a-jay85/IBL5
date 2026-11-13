<?php

declare(strict_types=1);

namespace JsbParser\Importers;

use JsbParser\AswFileParser;
use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;

class AswImporter
{
    private const AWARD_BY_EVENT = [
        'rookie_1' => ['Rookie-Sophomore Challenge', 'Rookie-Sophomore Challenge - MVP'],
        'rookie_2' => ['Rookie-Sophomore Challenge', 'Rookie-Sophomore Challenge - MVP'],
        'three_point' => ['Three-Point Contest', 'Three-Point Contest - Winner'],
        'dunk_contest' => ['Slam Dunk Competition', 'Slam Dunk Competition - Winner'],
    ];

    private const CONFERENCE_EVENTS = ['allstar_1', 'allstar_2'];

    private JsbImportRepositoryInterface $repository;

    public function __construct(JsbImportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function import(string $data, int $seasonYear, bool $rejectStaleRosters = false): JsbImportResult
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
        if ($rejectStaleRosters) {
            try {
                $staleYear = $this->repository->findOtherSeasonWithIdenticalRosters($seasonYear, $rosters);
            } catch (\RuntimeException $e) {
                $result->addError('ASW stale check failed; nothing written: ' . $e->getMessage());
                return $result;
            }
            if ($staleYear !== null) {
                $result->addMessage(sprintf(
                    'ASW rejected as stale: rosters are identical to season %d; nothing written for season %d',
                    $staleYear,
                    $seasonYear
                ));
                return $result;
            }
        }

        /** @var array<string, array<int, string|null>> $playerNames */
        $playerNames = [];
        foreach ($rosters as $eventType => $playerIds) {
            foreach ($playerIds as $slot => $pid) {
                $playerName = $this->repository->getPlayerName($pid);
                $playerNames[$eventType][$slot] = $playerName;

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
        $this->importParticipantAwards($rosters, $playerNames, $seasonYear, $result);

        return $result;
    }

    public function importFile(string $filePath, int $seasonYear): JsbImportResult
    {
        return FileReader::readOrFail($filePath, 'ASW', fn (string $data) => $this->import($data, $seasonYear));
    }

    /**
     * @param array<string, list<int>> $rosters
     * @param array<string, array<int, string|null>> $playerNames
     */
    private function importParticipantAwards(array $rosters, array $playerNames, int $seasonYear, JsbImportResult $result): void
    {
        $unnamed = 0;
        foreach ($rosters as $eventType => $playerIds) {
            foreach ($playerIds as $slot => $pid) {
                $name = $playerNames[$eventType][$slot] ?? null;
                if ($name === null || $name === '') {
                    $unnamed++;
                    continue;
                }
                try {
                    if (in_array($eventType, self::CONFERENCE_EVENTS, true)) {
                        $conference = $this->repository->findAllStarConference($pid, $seasonYear);
                        if ($conference !== 'Eastern' && $conference !== 'Western') {
                            $result->addMessage(sprintf('ASW award skipped: no conference for %s (pid %d) in %d', $name, $pid, $seasonYear));
                            continue;
                        }
                        $award = $conference . ' Conference All-Star';
                        $sibling = ($conference === 'Eastern' ? 'Western' : 'Eastern') . ' Conference All-Star';
                    } elseif (isset(self::AWARD_BY_EVENT[$eventType])) {
                        [$award, $sibling] = self::AWARD_BY_EVENT[$eventType];
                    } else {
                        continue;
                    }
                    $result->recordUpsert(
                        $this->repository->insertAwardUnlessFamilyExists($seasonYear, $award, $name, $sibling)
                    );
                } catch (\RuntimeException $e) {
                    $result->addError('All-Star award insert failed: ' . $e->getMessage());
                }
            }
        }
        if ($unnamed > 0) {
            $result->addMessage(sprintf('ASW awards: %d roster slots skipped (unknown player name)', $unnamed));
        }
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
