<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryValidatorInterface;

/**
 * @phpstan-import-type ProcessedSubmission from Contracts\DepthChartEntryProcessorInterface
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
     * @param ProcessedSubmission $depthChartData
     */
    public function validate(array $depthChartData, string $phase): bool
    {
        $this->errors = [];
        
        if ($phase === 'Playoffs') {
            $minActivePlayers = 10;
            $maxActivePlayers = 12;
            $minPositionDepth = 2;
        } else {
            $minActivePlayers = 12;
            $maxActivePlayers = 12;
            $minPositionDepth = 3;
        }
        
        $this->validateActivePlayerCount(
            $depthChartData['activePlayers'],
            $minActivePlayers,
            $maxActivePlayers
        );
        
        $positions = \JSB::PLAYER_POSITIONS;
        $this->validatePositionDepth($depthChartData['pos_1'], $positions[0], $minPositionDepth);
        $this->validatePositionDepth($depthChartData['pos_2'], $positions[1], $minPositionDepth);
        $this->validatePositionDepth($depthChartData['pos_3'], $positions[2], $minPositionDepth);
        $this->validatePositionDepth($depthChartData['pos_4'], $positions[3], $minPositionDepth);
        $this->validatePositionDepth($depthChartData['pos_5'], $positions[4], $minPositionDepth);
        
        $this->validateNoMultipleStartingPositions(
            $depthChartData['hasStarterAtMultiplePositions'],
            $depthChartData['nameOfProblemStarter'] ?? ''
        );
        
        return $this->errors === [];
    }
    
    private function validateActivePlayerCount(int $activePlayers, int $min, int $max): void
    {
        if ($activePlayers < $min) {
            $this->errors[] = [
                'type' => 'active_players_min',
                'message' => "You must have at least $min active players in your lineup; you have $activePlayers.",
                'detail' => "Please press the \"Back\" button on your browser and activate " . ($min - $activePlayers) . " player(s)."
            ];
        }
        
        if ($activePlayers > $max) {
            $this->errors[] = [
                'type' => 'active_players_max',
                'message' => "You can't have more than $max active players in your lineup; you have $activePlayers.",
                'detail' => "Please press the \"Back\" button on your browser and deactivate " . ($activePlayers - $max) . " player(s)."
            ];
        }
    }
    
    private function validatePositionDepth(int $count, string $position, int $min): void
    {
        if ($count < $min) {
            $this->errors[] = [
                'type' => 'position_depth',
                'message' => "You must have at least $min players entered in $position slot &mdash; you have $count.",
                'detail' => "Please click the \"Back\" button on your browser and activate " . ($min - $count) . " player(s)."
            ];
        }
    }
    
    private function validateNoMultipleStartingPositions(bool $hasMultiple, string $playerName): void
    {
        if ($hasMultiple) {
            $this->errors[] = [
                'type' => 'multiple_starting_positions',
                'message' => "$playerName is listed at more than one position in the starting lineup.",
                'detail' => "Please click the \"Back\" button on your browser and ensure they are only starting at ONE position."
            ];
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
            $message = \Utilities\HtmlSanitizer::safeHtmlOutput($error['message']);
            $detail = \Utilities\HtmlSanitizer::safeHtmlOutput($error['detail']);
            $html .= '<div style="text-align: center;"><span style="color: red;"><strong style="font-weight: bold;">' . $message . '</strong></span><p>' . $detail . '</p></div>';
        }
        return $html;
    }
}
