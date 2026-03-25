<?php

declare(strict_types=1);

namespace Voting;

use Voting\Contracts\VotingRepositoryInterface;
use Voting\Contracts\VotingSubmissionServiceInterface;

/**
 * VotingSubmissionService — Validates and persists vote submissions
 *
 * Collects ALL validation errors before returning, so users see every
 * issue at once instead of fix-one-resubmit-find-next.
 *
 * Error messages preserve exact substrings required by E2E tests.
 *
 * @phpstan-import-type EoyBallot from VotingRepositoryInterface
 * @phpstan-import-type AsgBallot from VotingRepositoryInterface
 *
 * @see VotingSubmissionServiceInterface
 */
class VotingSubmissionService implements VotingSubmissionServiceInterface
{
    /** @var array<string, string> EOY category code => display label (with article) */
    private const EOY_CATEGORIES = [
        'MVP' => 'an MVP',
        'Six' => 'a 6th Man of the Year',
        'ROY' => 'a Rookie of the Year',
        'GM'  => 'a GM of the Year',
    ];

    /** @var array<string, string> EOY category code => duplicate error label */
    private const EOY_DUPLICATE_LABELS = [
        'MVP' => 'MVP',
        'Six' => 'Sixth Man of the Year',
        'ROY' => 'Rookie of the Year',
        'GM'  => 'GM of the Year',
    ];

    /** @var array<string, array{prefix: string, label: string}> ASG position code => field prefix + display label */
    private const ASG_POSITIONS = [
        'ECF' => ['prefix' => 'East_F', 'label' => 'Eastern Frontcourt'],
        'ECB' => ['prefix' => 'East_B', 'label' => 'Eastern Backcourt'],
        'WCF' => ['prefix' => 'West_F', 'label' => 'Western Frontcourt'],
        'WCB' => ['prefix' => 'West_B', 'label' => 'Western Backcourt'],
    ];

    public function __construct(
        private readonly VotingRepositoryInterface $repository,
    ) {
    }

    /**
     * @see VotingSubmissionServiceInterface::submitEoyVote()
     *
     * @param EoyBallot $ballot
     */
    public function submitEoyVote(string $teamName, array $ballot): SubmissionResult
    {
        $errors = [];

        $this->validateEoySelfVotes($teamName, $ballot, $errors);
        $this->validateEoyEmptyFields($ballot, $errors);
        $this->validateEoyDuplicates($ballot, $errors);

        if ($errors !== []) {
            return SubmissionResult::withErrors($errors);
        }

        $this->repository->saveEoyVote($teamName, $ballot);
        $this->repository->markEoyVoteCast($teamName);

        return SubmissionResult::success();
    }

    /**
     * @see VotingSubmissionServiceInterface::submitAsgVote()
     *
     * @param AsgBallot $ballot
     * @param array<string, list<string>> $rawPostCategories
     */
    public function submitAsgVote(string $teamName, array $ballot, array $rawPostCategories): SubmissionResult
    {
        $errors = [];

        $this->validateAsgSelfVotes($teamName, $ballot, $errors);
        $this->validateAsgMissingVotes($ballot, $errors);
        $this->validateAsgTooManyVotes($rawPostCategories, $errors);

        if ($errors !== []) {
            return SubmissionResult::withErrors($errors);
        }

        $this->repository->saveAsgVote($teamName, $ballot);
        $this->repository->markAsgVoteCast($teamName);

        return SubmissionResult::success();
    }

    // ==================== EOY Validation ====================

    /**
     * @param EoyBallot $ballot
     * @param list<string> $errors
     */
    private function validateEoySelfVotes(string $teamName, array $ballot, array &$errors): void
    {
        // Player categories: MVP, Six, ROY
        $playerFields = [
            'MVP_1', 'MVP_2', 'MVP_3',
            'Six_1', 'Six_2', 'Six_3',
            'ROY_1', 'ROY_2', 'ROY_3',
        ];
        if ($teamName !== '') {
            foreach ($playerFields as $field) {
                if (str_contains($ballot[$field], $teamName)) {
                    $errors[] = 'Sorry, you cannot vote for your own player. Try again.';
                }
            }

            // GM category
            $gmFields = ['GM_1', 'GM_2', 'GM_3'];
            foreach ($gmFields as $field) {
                if (str_contains($ballot[$field], $teamName)) {
                    $errors[] = 'Sorry, you cannot vote for yourself. Try again.';
                }
            }
        }
    }

    /**
     * @param EoyBallot $ballot
     * @param list<string> $errors
     */
    private function validateEoyEmptyFields(array $ballot, array &$errors): void
    {
        foreach (self::EOY_CATEGORIES as $code => $label) {
            for ($i = 1; $i <= 3; $i++) {
                $field = "{$code}_{$i}";
                if ($ballot[$field] === '') {
                    $errors[] = "Sorry, you must select {$label}. Try again.";
                }
            }
        }
    }

    /**
     * @param EoyBallot $ballot
     * @param list<string> $errors
     */
    private function validateEoyDuplicates(array $ballot, array &$errors): void
    {
        foreach (self::EOY_DUPLICATE_LABELS as $code => $label) {
            $v1 = $ballot["{$code}_1"];
            $v2 = $ballot["{$code}_2"];
            $v3 = $ballot["{$code}_3"];

            $hasDuplicate = ($v1 !== '' && $v2 !== '' && $v1 === $v2)
                || ($v1 !== '' && $v3 !== '' && $v1 === $v3)
                || ($v2 !== '' && $v3 !== '' && $v2 === $v3);

            if ($hasDuplicate) {
                $errors[] = "Sorry, you have selected the same player for multiple {$label} slots. Try again.";
            }
        }
    }

    // ==================== ASG Validation ====================

    /**
     * @param AsgBallot $ballot
     * @param list<string> $errors
     */
    private function validateAsgSelfVotes(string $teamName, array $ballot, array &$errors): void
    {
        if ($teamName === '') {
            return;
        }

        foreach (self::ASG_POSITIONS as $pos) {
            $prefix = $pos['prefix'];
            $court = str_contains($prefix, 'F') ? 'Frontcourt' : 'Backcourt';
            for ($i = 1; $i <= 4; $i++) {
                $field = "{$prefix}{$i}";
                if (str_contains($ballot[$field], $teamName)) {
                    $errors[] = "Sorry, you cannot vote for your own player ({$court}: {$ballot[$field]}). Please go back, unselect that player, select a different player not on your team, and try again.";
                }
            }
        }
    }

    /**
     * @param AsgBallot $ballot
     * @param list<string> $errors
     */
    private function validateAsgMissingVotes(array $ballot, array &$errors): void
    {
        foreach (self::ASG_POSITIONS as $pos) {
            $prefix = $pos['prefix'];
            $label = $pos['label'];
            for ($i = 1; $i <= 4; $i++) {
                $field = "{$prefix}{$i}";
                if ($ballot[$field] === '') {
                    $errors[] = "Sorry, you selected less than FOUR {$label} players. Please go back, select FOUR players, and try again.";
                    break; // One error per category is enough
                }
            }
        }
    }

    /**
     * @param array<string, list<string>> $rawPostCategories
     * @param list<string> $errors
     */
    private function validateAsgTooManyVotes(array $rawPostCategories, array &$errors): void
    {
        foreach (self::ASG_POSITIONS as $code => $pos) {
            $label = $pos['label'];
            if (isset($rawPostCategories[$code]) && count($rawPostCategories[$code]) > 4) {
                $errors[] = "Sorry, you selected more than four {$label} players. Please go back, select FOUR players, and try again.";
            }
        }
    }
}
