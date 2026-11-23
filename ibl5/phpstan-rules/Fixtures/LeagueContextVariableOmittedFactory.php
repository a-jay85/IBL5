<?php

declare(strict_types=1);

namespace PHPStanRules\Fixtures;

final class LeagueContextVariableOmittedFactory
{
    public function build(\mysqli $db, object $holder): \Team\TeamRepository
    {
        $leagueContext = $holder->current();

        return new \Team\TeamRepository($db);
    }
}
