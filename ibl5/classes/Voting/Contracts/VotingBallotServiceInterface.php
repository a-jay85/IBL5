<?php

declare(strict_types=1);

namespace Voting\Contracts;

/**
 * VotingBallotServiceInterface - Contract for voting ballot business logic
 *
 * @phpstan-import-type BallotCategory from VotingBallotViewInterface
 *
 * @see \Voting\VotingBallotService For the concrete implementation
 */
interface VotingBallotServiceInterface
{
    /**
     * Get ballot data for the current voting phase
     *
     * @param string $voterTeamName Voter's team name
     * @param \Season $season Current season
     * @param \League $league League instance for candidate queries
     * @return list<BallotCategory> Categories with their candidates
     */
    public function getBallotData(
        string $voterTeamName,
        \Season $season,
        \League $league
    ): array;
}
