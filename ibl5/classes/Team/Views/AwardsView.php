<?php

declare(strict_types=1);

namespace Team\Views;

use Utilities\HtmlSanitizer;

/**
 * Pure renderer for GM history and team accomplishments.
 *
 * @phpstan-import-type GMTenureRow from \Team\Contracts\TeamRepositoryInterface
 * @phpstan-import-type GMAwardRow from \Team\Contracts\TeamRepositoryInterface
 */
class AwardsView
{
    /**
     * Render GM history: tenure list + awards list.
     *
     * @param list<GMTenureRow> $tenures
     * @param list<GMAwardRow> $awards
     */
    public function renderGmHistory(array $tenures, array $awards): string
    {
        $tenureHtml = $this->renderGMTenureList($tenures);
        $awardsHtml = $this->renderGMAwardsList($awards);

        if ($tenureHtml === '' && $awardsHtml === '') {
            return '';
        }

        $output = $tenureHtml;
        if ($awardsHtml !== '') {
            $output .= $awardsHtml;
        }

        return $output;
    }

    /**
     * Render team accomplishments.
     *
     * @param list<array{year: int, Award: string}> $awards
     */
    public function renderTeamAccomplishments(array $awards): string
    {
        return $this->renderAwardsList($awards);
    }

    /**
     * @param list<GMTenureRow> $tenures
     */
    private function renderGMTenureList(array $tenures): string
    {
        if ($tenures === []) {
            return '';
        }

        $output = '<ul class="team-awards-list">';

        foreach ($tenures as $tenure) {
            $start = $tenure['start_season_year'];
            $end = $tenure['end_season_year'];
            $endLabel = $end === null ? 'Present' : (string) $end;
            $username = HtmlSanitizer::e($tenure['gm_username']);
            $output .= "<li><span class=\"award-year\">$start-$endLabel</span> $username</li>";
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * @param list<GMAwardRow> $awards
     */
    private function renderGMAwardsList(array $awards): string
    {
        if ($awards === []) {
            return '';
        }

        $output = '<ul class="team-awards-list">';

        foreach ($awards as $award) {
            $year = $award['year'];
            $awardName = HtmlSanitizer::e($award['Award']);
            $output .= "<li><span class=\"award-year\">$year</span> $awardName</li>";
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * @param list<array{year: int, Award: string}> $awards
     */
    private function renderAwardsList(array $awards): string
    {
        if ($awards === []) {
            return '';
        }

        $output = '<ul class="team-awards-list">';

        foreach ($awards as $record) {
            $year = $record['year'];
            $sanitizedAward = HtmlSanitizer::e($record['Award']);
            $output .= "<li><span class=\"award-year\">$year</span> $sanitizedAward</li>";
        }

        $output .= '</ul>';

        return $output;
    }
}
