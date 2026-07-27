<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class AwaRepository extends \BaseMysqliRepository
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
}
