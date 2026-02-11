<?php

declare(strict_types=1);

namespace Voting\Contracts;

/**
 * VotingBallotViewInterface - Contract for voting ballot form rendering
 *
 * @phpstan-type BallotCategory array{
 *     code: string,
 *     title: string,
 *     instruction: string,
 *     candidates: list<array<string, mixed>>
 * }
 *
 * @see \Voting\VotingBallotView For the concrete implementation
 */
interface VotingBallotViewInterface
{
    /**
     * Render the complete ballot form
     *
     * @param string $formAction Form action URL
     * @param string $voterTeamName Voter's team name
     * @param int $tid Voter's team ID
     * @param string $phase Season phase
     * @param list<BallotCategory> $categories Ballot categories with candidates
     * @return string HTML output
     */
    public function renderBallotForm(
        string $formAction,
        string $voterTeamName,
        int $tid,
        string $phase,
        array $categories
    ): string;
}
