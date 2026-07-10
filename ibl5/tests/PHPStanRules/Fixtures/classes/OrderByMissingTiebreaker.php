<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\Classes;

final class OrderByMissingTiebreaker
{
    public function getByPct(): string
    {
        return "SELECT name, pct FROM ibl_standings ORDER BY pct DESC";
    }

    public function getByWins(): string
    {
        return "SELECT team, wins FROM ibl_standings ORDER BY wins DESC LIMIT 2";
    }
}
