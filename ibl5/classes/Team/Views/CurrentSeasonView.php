<?php

declare(strict_types=1);

namespace Team\Views;

use Utilities\HtmlSanitizer;

/**
 * Pure renderer for the current season team info list.
 *
 * @phpstan-import-type CurrentSeasonData from \Team\Contracts\TeamServiceInterface
 */
class CurrentSeasonView
{
    /**
     * Render team info list from pre-computed data.
     *
     * @param CurrentSeasonData $data
     */
    public function render(array $data): string
    {
        $teamName = HtmlSanitizer::e($data['teamName']);
        $arena = HtmlSanitizer::e($data['arena']);
        $conference = HtmlSanitizer::e($data['conference']);
        $division = HtmlSanitizer::e($data['division']);
        $homeRecord = HtmlSanitizer::e($data['homeRecord']);
        $awayRecord = HtmlSanitizer::e($data['awayRecord']);
        $wins = $data['wins'];
        $losses = $data['losses'];
        $confPos = $data['conferencePosition'];
        $divPos = $data['divisionPosition'];
        $gbDisplay = $data['divisionGB'];
        $lastWin = $data['lastWin'];
        $lastLoss = $data['lastLoss'];
        $capacity = $data['capacity'];

        $output = '<div class="team-info-list">'
            . '<span class="team-info-list__label">Team</span>'
            . "<span class=\"team-info-list__value\">$teamName</span>";

        if ($data['fka'] !== null) {
            $fka = HtmlSanitizer::e($data['fka']);
            $output .= '<span class="team-info-list__label">f.k.a.</span>'
                . "<span class=\"team-info-list__value\">$fka</span>";
        }

        $output .= '<span class="team-info-list__label">Record</span>'
            . "<span class=\"team-info-list__value\">$wins-$losses</span>"
            . '<span class="team-info-list__label">Arena</span>'
            . "<span class=\"team-info-list__value\">$arena</span>";

        if ($capacity !== 0) {
            $output .= '<span class="team-info-list__label">Capacity</span>'
                . "<span class=\"team-info-list__value\">$capacity</span>";
        }

        $output .= '<span class="team-info-list__label">Conference</span>'
            . "<span class=\"team-info-list__value\">$conference ($confPos" . self::ordinalSuffix($confPos) . ")</span>"
            . '<span class="team-info-list__label">Division</span>'
            . "<span class=\"team-info-list__value\">$division ($divPos" . self::ordinalSuffix($divPos) . ")</span>"
            . '<span class="team-info-list__label">Games Back</span>'
            . "<span class=\"team-info-list__value\">$gbDisplay</span>"
            . '<span class="team-info-list__label">Home</span>'
            . "<span class=\"team-info-list__value\">$homeRecord</span>"
            . '<span class="team-info-list__label">Road</span>'
            . "<span class=\"team-info-list__value\">$awayRecord</span>"
            . '<span class="team-info-list__label">Last 10</span>'
            . "<span class=\"team-info-list__value\">$lastWin-$lastLoss</span>"
            . '</div>';

        return $output;
    }

    /**
     * Return ordinal suffix for a position number (1st, 2nd, 3rd, etc.)
     */
    private static function ordinalSuffix(int $n): string
    {
        if ($n % 100 >= 11 && $n % 100 <= 13) {
            return 'th';
        }
        return match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
