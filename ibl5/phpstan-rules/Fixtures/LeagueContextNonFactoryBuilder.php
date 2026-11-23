<?php

declare(strict_types=1);

namespace PHPStanRules\Fixtures;

final class LeagueContextNonFactoryBuilder
{
    public static function createService(\mysqli $db, ?\League\LeagueContext $leagueContext = null): \OnThisDay\OnThisDayRepository
    {
        return new \OnThisDay\OnThisDayRepository($db);
    }
}
