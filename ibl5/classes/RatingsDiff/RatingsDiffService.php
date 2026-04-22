<?php

declare(strict_types=1);

namespace RatingsDiff;

use RatingsDiff\Contracts\RatingsDiffRepositoryInterface;

/**
 * RatingsDiffService — computes per-player rating deltas against the latest end-of-season snapshot.
 *
 * Column name notes (migration 113):
 *   - `do`  → r_drive_off (drive offense rating)
 *   - `to`  → r_trans_off (transition offense rating)
 *   - `r_to` → r_tvr      (turnover rating)
 *   - `sta` is excluded — ibl_plr_snapshots does not have that column.
 */
class RatingsDiffService implements Contracts\RatingsDiffServiceInterface
{
    /**
     * All 21 rating fields present in both ibl_plr and ibl_plr_snapshots.
     *
     * @var list<string>
     */
    public const RATED_FIELDS = [
        'r_fga', 'r_fgp', 'r_fta', 'r_ftp', 'r_3ga', 'r_3gp',
        'r_orb', 'r_drb', 'r_ast', 'r_stl', 'r_tvr', 'r_blk', 'r_foul',
        'oo', 'r_drive_off', 'po', 'r_trans_off', 'od', 'dd', 'pd', 'td',
    ];

    public function __construct(
        private readonly RatingsDiffRepositoryInterface $repository,
    ) {
    }

    /**
     * @see Contracts\RatingsDiffServiceInterface::getDiffs()
     *
     * @return list<RatingRow>
     */
    public function getDiffs(?int $overrideYear = null, ?int $filterTid = null): array
    {
        $baselineYear = $overrideYear ?? $this->repository->getLatestEndOfSeasonYear();
        if ($baselineYear === null) {
            return [];
        }

        $dbRows = $this->repository->getDiffRows($baselineYear, $filterTid);

        /** @var list<RatingRow> $realRows */
        $realRows = [];
        /** @var list<RatingRow> $newRows */
        $newRows = [];

        foreach ($dbRows as $row) {
            $ratingRow = $this->buildRatingRow($row);
            if ($ratingRow->isNewPlayer) {
                $newRows[] = $ratingRow;
            } else {
                $realRows[] = $ratingRow;
            }
        }

        usort($realRows, static function (RatingRow $a, RatingRow $b): int {
            if ($a->maxAbsDelta !== $b->maxAbsDelta) {
                return $b->maxAbsDelta - $a->maxAbsDelta;
            }
            if ($a->sumAbsDelta !== $b->sumAbsDelta) {
                return $b->sumAbsDelta - $a->sumAbsDelta;
            }
            return strcmp($a->name, $b->name);
        });

        usort($newRows, static fn (RatingRow $a, RatingRow $b): int => strcmp($a->name, $b->name));

        return array_values(array_merge($realRows, $newRows));
    }

    /**
     * @see Contracts\RatingsDiffServiceInterface::getBaselineYear()
     */
    public function getBaselineYear(?int $overrideYear = null): ?int
    {
        return $overrideYear ?? $this->repository->getLatestEndOfSeasonYear();
    }

    /**
     * Builds a RatingRow from a raw DB row.
     *
     * @param array<string, mixed> $row
     */
    private function buildRatingRow(array $row): RatingRow
    {
        $pid      = $this->readInt($row, 'pid');
        $name     = is_string($row['name'] ?? null) ? $row['name'] : '';
        $pos      = is_string($row['pos'] ?? null) ? $row['pos'] : '';
        $age      = $this->readIntOrNull($row, 'age');
        $teamid      = $this->readInt($row, 'teamid');
        $teamName   = is_string($row['team_name'] ?? null) ? $row['team_name'] : null;
        $teamColor1 = is_string($row['color1'] ?? null) ? $row['color1'] : 'FFFFFF';
        $teamColor2 = is_string($row['color2'] ?? null) ? $row['color2'] : '000000';

        // Use s_oo as the discriminator: if null, the player has no snapshot (LEFT JOIN miss)
        $isNewPlayer = ($this->readIntOrNull($row, 's_oo') === null);

        /** @var array<string, RatingDelta> $deltas */
        $deltas = [];
        foreach (self::RATED_FIELDS as $field) {
            $after  = $this->readInt($row, $field);
            $before = $isNewPlayer ? null : $this->readIntOrNull($row, 's_' . $field);
            $delta  = ($before === null) ? null : ($after - $before);
            $deltas[$field] = new RatingDelta($field, $before, $after, $delta);
        }

        $maxAbsDelta = 0;
        $sumAbsDelta = 0;
        foreach ($deltas as $ratingDelta) {
            if ($ratingDelta->delta !== null) {
                $abs = abs($ratingDelta->delta);
                if ($abs > $maxAbsDelta) {
                    $maxAbsDelta = $abs;
                }
                $sumAbsDelta += $abs;
            }
        }

        return new RatingRow(
            pid: $pid,
            name: $name,
            pos: $pos,
            age: $age,
            teamid: $teamid,
            teamName: $teamName,
            teamColor1: $teamColor1,
            teamColor2: $teamColor2,
            deltas: $deltas,
            maxAbsDelta: $maxAbsDelta,
            sumAbsDelta: $sumAbsDelta,
            isNewPlayer: $isNewPlayer,
        );
    }

    /**
     * Reads an integer value from a DB row, defaulting to 0 if missing or non-numeric.
     *
     * @param array<string, mixed> $row
     */
    private function readInt(array $row, string $key): int
    {
        $val = $row[$key] ?? null;
        if (is_int($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (int) $val;
        }
        return 0;
    }

    /**
     * Reads a nullable integer from a DB row. Returns null if the value is null or non-numeric.
     *
     * @param array<string, mixed> $row
     */
    private function readIntOrNull(array $row, string $key): ?int
    {
        $val = $row[$key] ?? null;
        if ($val === null) {
            return null;
        }
        if (is_int($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (int) $val;
        }
        return null;
    }
}
