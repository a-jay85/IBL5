<?php

declare(strict_types=1);

namespace PHPStanRules\Fixtures;

final class LeagueContextNearMissFactory
{
    public function build(\mysqli $db, object $holder): \OnThisDay\OnThisDayRepository
    {
        $league = $holder->league();
        $leagueCtx = $holder->ctx();
        $holder->leagueContextLabel();

        return new \OnThisDay\OnThisDayRepository($db);
    }
}
