<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryValidatorInterface;

/**
 * @phpstan-import-type ValidatorInput from Contracts\DepthChartEntryValidatorInterface
 * @phpstan-import-type ValidationError from Contracts\DepthChartEntryValidatorInterface
 *
 * @see DepthChartEntryValidatorInterface
 */
class DepthChartEntryValidator implements DepthChartEntryValidatorInterface
{
    /** @var list<ValidationError> */
    private array $errors = [];
    
    /**
     * @see DepthChartEntryValidatorInterface::validate()
     * @param ValidatorInput $depthChartData
     */
    public function validate(array $depthChartData, string $phase): bool
    {
        $this->errors = [];

        if ($phase === 'Playoffs') {
            $minActivePlayers = 10;
            $maxActivePlayers = 12;
            $minPerPosition = 2;
        } else {
            $minActivePlayers = 12;
            $maxActivePlayers = 12;
            $minPerPosition = 3;
        }

        $this->validateActivePlayerCount(
            $depthChartData['activePlayers'],
            $minActivePlayers,
            $maxActivePlayers
        );

        $this->validatePositionDepth($depthChartData, $minPerPosition);

        if ($depthChartData['hasStarterAtMultiplePositions']) {
            $this->errors[] = [
                'type' => 'multiple_starting_positions',
                'message' => $depthChartData['nameOfProblemStarter'] . ' is set as starter (1st) at multiple positions.',
                'detail' => 'Set this player as 1st at only one position and resubmit.'
            ];
        }

        return $this->errors === [];
    }

    /**
     * @see DepthChartEntryValidatorInterface::validateRoster()
     * @param list<int> $submittedPids
     * @param list<int> $rosterPids
     */
    public function validateRoster(array $submittedPids, array $rosterPids): bool
    {
        $this->errors = [];

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

        if ($foreign !== []) {
            $this->errors[] = [
                'type' => 'roster_foreign_pid',
                'message' => 'Your submission includes a player who is not on your roster (pid: ' . implode(', ', $foreign) . ').',
                'detail' => 'Reload the depth chart form so it lists only your current roster, then resubmit.',
            ];
        }
        if ($duplicates !== []) {
            $this->errors[] = [
                'type' => 'roster_duplicate_pid',
                'message' => 'A player appears more than once in your submission (pid: ' . implode(', ', $duplicates) . ').',
                'detail' => 'Each roster player may appear only once. Reload the form and resubmit.',
            ];
        }
        if ($missing !== []) {
            $this->errors[] = [
                'type' => 'roster_missing_pid',
                'message' => 'Your submission is missing a roster player (pid: ' . implode(', ', $missing) . ').',
                'detail' => 'Every player on your roster must be included, even when inactive. Reload the form and resubmit.',
            ];
        }

        return $this->errors === [];
    }

    private function validateActivePlayerCount(int $activePlayers, int $min, int $max): void
    {
        if ($activePlayers < $min) {
            $this->errors[] = [
                'type' => 'active_players_min',
                'message' => "You must have at least $min active players in your lineup; you have $activePlayers.",
                'detail' => 'Activate ' . ($min - $activePlayers) . ' more player(s) below and resubmit.',
            ];
        }

        if ($activePlayers > $max) {
            $this->errors[] = [
                'type' => 'active_players_max',
                'message' => "You can't have more than $max active players in your lineup; you have $activePlayers.",
                'detail' => 'Deactivate ' . ($activePlayers - $max) . ' player(s) below and resubmit.',
            ];
        }
    }

    /**
     * @param ValidatorInput $depthChartData
     */
    private function validatePositionDepth(array $depthChartData, int $minPerPosition): void
    {
        $positionNames = ['PG', 'SG', 'SF', 'PF', 'C'];
        $positionKeys = ['pos_1', 'pos_2', 'pos_3', 'pos_4', 'pos_5'];

        foreach ($positionKeys as $index => $key) {
            $count = $depthChartData[$key];
            if ($count < $minPerPosition) {
                $posName = $positionNames[$index];
                $this->errors[] = [
                    'type' => 'position_depth',
                    'message' => "You need at least $minPerPosition non-injured players assigned to {$posName}; you have $count.",
                    'detail' => "Assign more players to the {$posName} position below and resubmit.",
                ];
            }
        }
    }
    
    /**
     * @see DepthChartEntryValidatorInterface::getErrors()
     * @return list<ValidationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * @see DepthChartEntryValidatorInterface::getErrorMessagesHtml()
     */
    public function getErrorMessagesHtml(): string
    {
        $html = '';
        foreach ($this->errors as $error) {
            $message = \Security\HtmlSanitizer::safeHtmlOutput($error['message']);
            $detail = \Security\HtmlSanitizer::safeHtmlOutput($error['detail']);
            $html .= '<div class="text-center"><span class="text-red-500"><strong>' . $message . '</strong></span><p>' . $detail . '</p></div>';
        }
        return $html;
    }
}
