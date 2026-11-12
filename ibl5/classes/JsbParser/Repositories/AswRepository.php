<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class AswRepository extends \Database\BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @param array{season_year: int, event_type: string, roster_slot: int, pid: int|null, player_name: string|null} $record
     */
    public function upsertAllStarRoster(array $record): int
    {
        return $this->execute(
            'INSERT INTO `ibl_jsb_allstar_rosters`
                (season_year, event_type, roster_slot, pid, player_name)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pid = VALUES(pid),
                player_name = VALUES(player_name)',
            'isiis',
            $record['season_year'],
            $record['event_type'],
            $record['roster_slot'],
            $record['pid'],
            $record['player_name']
        );
    }

    /**
     * @param array{season_year: int, contest_type: string, round: int, participant_slot: int, pid: int|null, score: int} $record
     */
    public function upsertAllStarScore(array $record): int
    {
        return $this->execute(
            'INSERT INTO `ibl_jsb_allstar_scores`
                (season_year, contest_type, round, participant_slot, pid, score)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pid = VALUES(pid),
                score = VALUES(score)',
            'isiiii',
            $record['season_year'],
            $record['contest_type'],
            $record['round'],
            $record['participant_slot'],
            $record['pid'],
            $record['score']
        );
    }

    private const EVENT_TYPES = ['allstar_1', 'allstar_2', 'rookie_1', 'rookie_2', 'three_point', 'dunk_contest'];

    /**
     * Return another season whose six stored roster lists equal $rosters, or null.
     *
     * @param array<string, list<int>> $rosters event_type => pids in slot order
     */
    public function findOtherSeasonWithIdenticalRosters(int $seasonYear, array $rosters): ?int
    {
        /** @var list<array{season_year: int, event_type: string, roster_slot: int, pid: int|null}> $rows */
        $rows = $this->fetchAll(
            // @phpstan-ignore ibl.orderByMissingTiebreaker (roster_slot is unique within season_year + event_type)
            'SELECT season_year, event_type, roster_slot, pid
               FROM `ibl_jsb_allstar_rosters`
              WHERE season_year <> ?
              ORDER BY season_year, event_type, roster_slot',
            'i',
            $seasonYear
        );
        /** @var array<int, array<string, list<int|null>>> $stored */
        $stored = [];
        foreach ($rows as $row) {
            $stored[$row['season_year']][$row['event_type']][] = $row['pid'];
        }
        $target = self::normalizeRosters($rosters);
        foreach ($stored as $year => $byEvent) {
            if (self::normalizeRosters($byEvent) === $target) {
                return $year;
            }
        }
        return null;
    }

    /**
     * @param array<string, list<int|null>> $byEvent
     * @return list<list<int|null>>
     */
    private static function normalizeRosters(array $byEvent): array
    {
        $out = [];
        foreach (self::EVENT_TYPES as $type) {
            $out[] = array_values($byEvent[$type] ?? []);
        }
        return $out;
    }

    /** Conference ('Eastern'|'Western') of the player's team that season: ibl_hist first, ibl_plr fallback. */
    public function findAllStarConference(int $pid, int $seasonYear): ?string
    {
        /** @var array{conference: string}|null $row */
        $row = $this->fetchOne(
            'SELECT lc.conference
               FROM `ibl_league_config` lc
              WHERE lc.season_ending_year = ?
                AND lc.teamid = COALESCE(
                    (SELECT h.teamid FROM `ibl_hist` h WHERE h.pid = ? AND h.year = ? LIMIT 1),
                    (SELECT p.teamid FROM `ibl_plr` p WHERE p.pid = ?)
                )
              LIMIT 1',
            'iiii',
            $seasonYear,
            $pid,
            $seasonYear,
            $pid
        );
        return $row === null ? null : $row['conference'];
    }

    /** Insert (year, award, name) unless that row or its family sibling exists. Returns 1 inserted, 0 skipped. */
    public function insertAwardUnlessFamilyExists(int $year, string $award, string $name, string $siblingAward): int
    {
        return $this->execute(
            'INSERT INTO `ibl_awards` (year, award, name)
             SELECT ?, ?, ? FROM DUAL
              WHERE NOT EXISTS (
                  SELECT 1 FROM `ibl_awards`
                   WHERE year = ? AND name = ? AND award IN (?, ?)
              )',
            'ississs',
            $year,
            $award,
            $name,
            $year,
            $name,
            $award,
            $siblingAward
        );
    }
}
