<?php

declare(strict_types=1);

namespace PHPStanRules\Fixtures;

final class LeagueContextPassedFactory
{
    /** @return array{\OnThisDay\OnThisDayRepository, \Voting\VotingRepository} */
    public function build(\mysqli $db, \League\LeagueContext $leagueContext): array
    {
        return [
            new \OnThisDay\OnThisDayRepository($db, $leagueContext),
            new \Voting\VotingRepository($db, leagueContext: $leagueContext),
        ];
    }
}
