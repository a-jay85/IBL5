<?php

declare(strict_types=1);

namespace DepthChart;

use DepthChart\Contracts\DepthChartValidatorInterface;
use Validation\ValidationError;
use Validation\ValidationResultWithContext;

/**
 * @phpstan-import-type ValidatorInput from Contracts\DepthChartValidatorInterface
 *
 * @see DepthChartValidatorInterface
 */
class DepthChartValidator implements DepthChartValidatorInterface
{
    /**
     * @see DepthChartValidatorInterface::validate()
     * @param ValidatorInput $depthChartData
     * @return ValidationResultWithContext<null>
     */
    public function validate(array $depthChartData, string $phase): ValidationResultWithContext
    {
        if ($phase === 'Playoffs') {
            $minActivePlayers = 10;
            $maxActivePlayers = 12;
            $minPerPosition = 2;
        } else {
            $minActivePlayers = 12;
            $maxActivePlayers = 12;
            $minPerPosition = 3;
        }

        $errors = $this->validateActivePlayerCount(
            $depthChartData['activePlayers'],
            $minActivePlayers,
            $maxActivePlayers
        );
        $errors = array_merge($errors, $this->validatePositionDepth($depthChartData, $minPerPosition));

        if ($depthChartData['hasStarterAtMultiplePositions']) {
            $errors[] = new ValidationError(
                'multiple_starting_positions',
                $depthChartData['nameOfProblemStarter'] . ' is set as starter (1st) at multiple positions.',
                'Set this player as 1st at only one position and resubmit.'
            );
        }

        return ValidationResultWithContext::fromErrors($errors, null);
    }

    /**
     * @see DepthChartValidatorInterface::validateRoster()
     * @param list<int> $submittedPids
     * @param list<int> $rosterPids
     * @return ValidationResultWithContext<null>
     */
    public function validateRoster(array $submittedPids, array $rosterPids): ValidationResultWithContext
    {
        $rosterSet = array_flip($rosterPids);
        $submittedSet = array_flip($submittedPids);

        $foreign = array_values(array_unique(array_filter(
            $submittedPids,
            static fn (int $pid): bool => !isset($rosterSet[$pid])
        )));
        $duplicates = array_values(array_unique(array_diff_key($submittedPids, array_unique($submittedPids))));
        $missing = array_values(array_filter(
            $rosterPids,
            static fn (int $pid): bool => !isset($submittedSet[$pid])
        ));

        $errors = [];
        if ($foreign !== []) {
            $errors[] = new ValidationError(
                'roster_foreign_pid',
                'Your submission includes a player who is not on your roster (pid: ' . implode(', ', $foreign) . ').',
                'Reload the depth chart form so it lists only your current roster, then resubmit.',
            );
        }
        if ($duplicates !== []) {
            $errors[] = new ValidationError(
                'roster_duplicate_pid',
                'A player appears more than once in your submission (pid: ' . implode(', ', $duplicates) . ').',
                'Each roster player may appear only once. Reload the form and resubmit.',
            );
        }
        if ($missing !== []) {
            $errors[] = new ValidationError(
                'roster_missing_pid',
                'Your submission is missing a roster player (pid: ' . implode(', ', $missing) . ').',
                'Every player on your roster must be included, even when inactive. Reload the form and resubmit.',
            );
        }

        return ValidationResultWithContext::fromErrors($errors, null);
    }

    /** @return list<ValidationError> */
    private function validateActivePlayerCount(int $activePlayers, int $min, int $max): array
    {
        $errors = [];

        if ($activePlayers < $min) {
            $errors[] = new ValidationError(
                'active_players_min',
                "You must have at least $min active players in your lineup; you have $activePlayers.",
                'Activate ' . ($min - $activePlayers) . ' more player(s) below and resubmit.',
            );
        }

        if ($activePlayers > $max) {
            $errors[] = new ValidationError(
                'active_players_max',
                "You can't have more than $max active players in your lineup; you have $activePlayers.",
                'Deactivate ' . ($activePlayers - $max) . ' player(s) below and resubmit.',
            );
        }

        return $errors;
    }

    /**
     * @param ValidatorInput $depthChartData
     * @return list<ValidationError>
     */
    private function validatePositionDepth(array $depthChartData, int $minPerPosition): array
    {
        $errors = [];
        $positionNames = ['PG', 'SG', 'SF', 'PF', 'C'];
        $positionKeys = ['pos_1', 'pos_2', 'pos_3', 'pos_4', 'pos_5'];

        foreach ($positionKeys as $index => $key) {
            $count = $depthChartData[$key];
            if ($count < $minPerPosition) {
                $posName = $positionNames[$index];
                $errors[] = new ValidationError(
                'position_depth',
                "You need at least $minPerPosition non-injured players assigned to {$posName}; you have $count.",
                "Assign more players to the {$posName} position below and resubmit.",
            );
            }
        }

        return $errors;
    }
}
