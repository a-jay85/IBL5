<?php

declare(strict_types=1);

namespace PHPStanRules\Fixtures;

final class LeagueContextAbsentFactory
{
    public function build(\mysqli $db): \OnThisDay\OnThisDayRepository
    {
        return new \OnThisDay\OnThisDayRepository($db);
    }
}
