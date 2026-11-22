<?php

declare(strict_types=1);

namespace PHPStanRules\Fixtures;

final class LeagueContextCallOmittedFactory
{
    /** @return array{\Voting\VotingRepository, \League\LeagueContext} */
    public function build(\mysqli $db, \Module\ModuleServices $services): array
    {
        $repo = new \Voting\VotingRepository($db);

        return [$repo, $services->leagueContext()];
    }
}
