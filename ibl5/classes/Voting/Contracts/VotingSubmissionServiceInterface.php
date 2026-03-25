<?php

declare(strict_types=1);

namespace Voting\Contracts;

use Voting\SubmissionResult;

/**
 * VotingSubmissionServiceInterface — Validation and persistence for vote submissions
 *
 * Collects ALL validation errors (not just the first) so users see every issue at once.
 *
 * @phpstan-import-type EoyBallot from VotingRepositoryInterface
 * @phpstan-import-type AsgBallot from VotingRepositoryInterface
 */
interface VotingSubmissionServiceInterface
{
    /**
     * Validate and save an end-of-year ballot
     *
     * Validation order:
     * 1. Self-vote check (team name appears in any selection)
     * 2. Empty selection check (any field is blank)
     * 3. Duplicate selection check (same player in multiple slots of one category)
     *
     * On success, saves the ballot and marks the vote as cast.
     *
     * @param string $teamName Voter's team name
     * @param EoyBallot $ballot All 12 ballot selections
     */
    public function submitEoyVote(string $teamName, array $ballot): SubmissionResult;

    /**
     * Validate and save an All-Star ballot
     *
     * Validation order:
     * 1. Self-vote check (team name appears in any selection)
     * 2. Missing vote check (any position field is blank)
     * 3. Too many votes check (more than 4 per category)
     *
     * On success, saves the ballot and marks the vote as cast.
     *
     * @param string $teamName Voter's team name
     * @param AsgBallot $ballot All 16 ballot selections
     * @param array<string, list<string>> $rawPostCategories Raw $_POST arrays per category code (for count validation)
     */
    public function submitAsgVote(string $teamName, array $ballot, array $rawPostCategories): SubmissionResult;
}
