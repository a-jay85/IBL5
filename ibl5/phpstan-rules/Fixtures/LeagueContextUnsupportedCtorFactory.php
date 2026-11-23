<?php

declare(strict_types=1);

namespace PHPStanRules\Fixtures;

final class LeagueContextUnsupportedCtorFactory
{
    public function build(\mysqli $db, \League\LeagueContext $leagueContext): \DepthChart\DepthChartRepository
    {
        return new \DepthChart\DepthChartRepository($db);
    }
}
