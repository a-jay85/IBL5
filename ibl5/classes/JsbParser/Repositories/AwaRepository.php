<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class AwaRepository extends \Database\BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    public function upsertAward(int $year, string $award, string $name): int
    {
        return $this->execute(
            "INSERT INTO `ibl_awards` (year, award, name)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name)",
            'iss',
            $year,
            $award,
            $name
        );
    }

    /**
     * Check if the .awa stat-leader awards (e.g. "Scoring Leader (1st)") are
     * stored for a season. Other ibl_awards rows (All-Star, contests, Finals
     * MVP) come from other writers, so only the rows .awa writes count.
     */
    public function hasStatLeaderAwardsForYear(int $year): bool
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `ibl_awards`
             WHERE year = ? AND award LIKE '% Leader (%)'",
            'i',
            $year,
        );

        if ($row === null) {
            return false;
        }

        $cnt = $row['cnt'];

        return is_int($cnt) && $cnt > 0;
    }
}
