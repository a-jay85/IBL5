<?php

declare(strict_types=1);

namespace Voting\Contracts;

/**
 * VotingSubmissionViewInterface — HTML rendering for vote submission outcomes
 *
 * @phpstan-import-type EoyBallot from VotingRepositoryInterface
 * @phpstan-import-type AsgBallot from VotingRepositoryInterface
 */
interface VotingSubmissionViewInterface
{
    /**
     * Render validation error messages
     *
     * @param list<string> $errors One or more error messages to display
     */
    public function renderErrors(array $errors): string;

    /**
     * Render end-of-year vote confirmation with ballot recap
     *
     * @param string $teamName Voter's team name
     * @param EoyBallot $ballot The 12 saved selections
     */
    public function renderEoyConfirmation(string $teamName, array $ballot): string;

    /**
     * Render All-Star vote confirmation with ballot recap
     *
     * @param string $teamName Voter's team name
     * @param AsgBallot $ballot The 16 saved selections
     */
    public function renderAsgConfirmation(string $teamName, array $ballot): string;
}
