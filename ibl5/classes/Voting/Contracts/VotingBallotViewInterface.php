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
     * @param int $teamid Voter's team ID
     * @param string $phase Season phase
     * @param list<BallotCategory> $categories Ballot categories with candidates
     * @param array<string, array<int, string>> $selections Previously submitted picks keyed by category code
     *        (ASG: 0-indexed list per checkbox group; EOY: keys 1/2/3 = rank). Empty on a fresh GET.
     * @return string HTML output
     */
    public function renderBallotForm(
        string $formAction,
        string $voterTeamName,
        int $teamid,
        string $phase,
        array $categories,
        array $selections = []
    ): string;

    /**
     * Collapsed admin-only "Voting Results" block.
     *
     * @param string $resultsHtml Fully rendered output of
     *        VotingResultsControllerInterface::render(); embedded as-is.
     * @return string HTML output
     */
    public function renderResultsExpander(string $resultsHtml): string;
}
