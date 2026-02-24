<?php

declare(strict_types=1);

namespace Voting;

use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;
use Voting\Contracts\VotingBallotViewInterface;

/**
 * VotingBallotView - HTML rendering for the voting ballot form
 *
 * All player names and team names are XSS-protected in form values.
 *
 * @phpstan-import-type BallotCategory from VotingBallotViewInterface
 *
 * @see VotingBallotViewInterface For the interface contract
 */
class VotingBallotView implements VotingBallotViewInterface
{
    /**
     * @see VotingBallotViewInterface::renderBallotForm()
     *
     * @param list<BallotCategory> $categories
     */
    public function renderBallotForm(
        string $formAction,
        string $voterTeamName,
        int $tid,
        string $phase,
        array $categories
    ): string {
        $isASG = ($phase === 'Regular Season');
        $formName = $isASG ? 'ASGVote' : 'EOYVote';

        $safeFormAction = HtmlSanitizer::safeHtmlOutput($formAction);
        $safeVoterTeam = HtmlSanitizer::safeHtmlOutput($voterTeamName);

        $html = "<form name=\"{$formName}\" method=\"post\" action=\"{$safeFormAction}\">";
        $html .= '<div class="voting-form-container">';
        $html .= "<img src=\"images/logo/{$tid}.jpg\" alt=\"Team Logo\" class=\"team-logo-banner\">";
        $html .= '<button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--lg">Submit Votes!</button>';

        foreach ($categories as $category) {
            $html .= $this->renderShowHideScript($category['code']);
            $html .= $this->renderCategoryHeader($category['code'], $category['title'], $category['instruction']);
            $html .= $this->renderCandidateTable($category['code'], $category['candidates'], $voterTeamName, $phase);
        }

        $html .= "<input type=\"hidden\" name=\"teamname\" value=\"{$safeVoterTeam}\">";
        $html .= '<button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--lg">Submit Votes!</button>';
        $html .= '</div>';
        $html .= '</form>';

        return $html;
    }

    /**
     * Render JavaScript for show/hide toggle
     */
    private function renderShowHideScript(string $categoryCode): string
    {
        return "<script>
function ShowAndHide{$categoryCode}() {
    var x = document.getElementById('{$categoryCode}');
    if (x.style.display == 'none') {
        x.style.display = '';
    } else {
        x.style.display = 'none';
    }
}
</script>";
    }

    /**
     * Render a category header with toggle
     */
    private function renderCategoryHeader(string $code, string $title, string $instruction): string
    {
        $safeTitle = HtmlSanitizer::safeHtmlOutput($title);
        $safeInstruction = HtmlSanitizer::safeHtmlOutput($instruction);

        return "<div class=\"voting-category\" onclick=\"ShowAndHide{$code}()\">"
            . "<h2 class=\"ibl-title voting-category-title\">{$safeTitle}</h2>"
            . "<p class=\"voting-category-instruction\">{$safeInstruction}</p>"
            . '</div>';
    }

    /**
     * Render the candidate table for a voting category
     *
     * @param string $categoryCode Category code (ECF, MVP, GM, etc.)
     * @param list<array<string, mixed>> $candidates Candidate data
     * @param string $voterTeamName Voter's team name (to disable self-voting)
     * @param string $phase Season phase
     */
    private function renderCandidateTable(
        string $categoryCode,
        array $candidates,
        string $voterTeamName,
        string $phase
    ): string {
        $isASG = ($phase === 'Regular Season');
        $isGM = ($categoryCode === 'GM');

        $html = "<table id=\"{$categoryCode}\" style=\"display:none\" class=\"sortable ibl-data-table voting-form-table\">";
        $html .= '<thead><tr>';
        $html .= $this->renderTableHeaders($isASG, $isGM);
        $html .= '</tr></thead><tbody>';

        foreach ($candidates as $candidate) {
            $html .= $this->renderCandidateRow($categoryCode, $candidate, $voterTeamName, $isASG, $isGM);
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Render table column headers based on voting type
     */
    private function renderTableHeaders(bool $isASG, bool $isGM): string
    {
        $html = '';
        if ($isASG) {
            $html .= '<th>Vote</th>';
        } else {
            $html .= '<th>1st</th><th>2nd</th><th>3rd</th>';
        }

        if ($isGM) {
            $html .= '<th>Name</th><th>Team</th>';
        } else {
            $html .= '<th>Name</th><th>GM</th><th>GS</th><th>MIN</th>'
                . '<th>FGM</th><th>FGA</th><th>FG%</th>'
                . '<th>FTM</th><th>FTA</th><th>FT%</th>'
                . '<th>3PM</th><th>3PA</th><th>3P%</th>'
                . '<th>ORB</th><th>DRB</th><th>REB</th>'
                . '<th>AST</th><th>STL</th><th>TO</th>'
                . '<th>BLK</th><th>PF</th><th>PTS</th>'
                . '<th>2x2</th><th>3x2</th>';
        }

        return $html;
    }

    /**
     * Render a single candidate row
     *
     * @param array<string, mixed> $candidate
     */
    private function renderCandidateRow(
        string $categoryCode,
        array $candidate,
        string $voterTeamName,
        bool $isASG,
        bool $isGM
    ): string {
        /** @var string $name */
        $name = $candidate['name'] ?? '';
        /** @var string $teamName */
        $teamName = $candidate['teamName'] ?? '';

        $safeName = HtmlSanitizer::safeHtmlOutput($name);
        $safeTeamName = HtmlSanitizer::safeHtmlOutput($teamName);
        $safeValue = $safeName . ', ' . $safeTeamName;

        $isSameTeam = str_contains($teamName, $voterTeamName);

        $html = '<tr>';

        // Vote input columns
        $html .= $this->renderVoteInputs($categoryCode, $safeValue, $isSameTeam, $isASG);

        // Data columns
        if ($isGM) {
            $html .= "<td>{$safeName}</td><td>{$safeTeamName}</td>";
        } else {
            /** @var \Player\PlayerStats $stats */
            $stats = $candidate['stats'];
            /** @var int $playerID */
            $playerID = $candidate['playerID'];
            $playerThumbnail = PlayerImageHelper::renderThumbnail($playerID);

            $html .= "<td class=\"ibl-player-cell\">{$playerThumbnail}{$safeName}, {$safeTeamName}</td>"
                . "<td>{$stats->seasonGamesPlayed}</td>"
                . "<td>{$stats->seasonGamesStarted}</td>"
                . "<td>{$stats->seasonMinutesPerGame}</td>"
                . "<td>{$stats->seasonFieldGoalsMadePerGame}</td>"
                . "<td>{$stats->seasonFieldGoalsAttemptedPerGame}</td>"
                . "<td>{$stats->seasonFieldGoalPercentage}</td>"
                . "<td>{$stats->seasonFreeThrowsMadePerGame}</td>"
                . "<td>{$stats->seasonFreeThrowsAttemptedPerGame}</td>"
                . "<td>{$stats->seasonFreeThrowPercentage}</td>"
                . "<td>{$stats->seasonThreePointersMadePerGame}</td>"
                . "<td>{$stats->seasonThreePointersAttemptedPerGame}</td>"
                . "<td>{$stats->seasonThreePointPercentage}</td>"
                . "<td>{$stats->seasonOffensiveReboundsPerGame}</td>"
                . "<td>{$stats->seasonDefensiveReboundsPerGame}</td>"
                . "<td>{$stats->seasonTotalReboundsPerGame}</td>"
                . "<td>{$stats->seasonAssistsPerGame}</td>"
                . "<td>{$stats->seasonStealsPerGame}</td>"
                . "<td>{$stats->seasonTurnoversPerGame}</td>"
                . "<td>{$stats->seasonBlocksPerGame}</td>"
                . "<td>{$stats->seasonPersonalFoulsPerGame}</td>"
                . "<td>{$stats->seasonPointsPerGame}</td>"
                . "<td>{$stats->seasonDoubleDoubles}</td>"
                . "<td>{$stats->seasonTripleDoubles}</td>";
        }

        $html .= '</tr>';

        return $html;
    }

    /**
     * Render vote input columns (checkbox for ASG, radio buttons for EOY)
     */
    private function renderVoteInputs(
        string $categoryCode,
        string $safeValue,
        bool $isSameTeam,
        bool $isASG
    ): string {
        if ($isSameTeam) {
            return $isASG ? '<td></td>' : '<td></td><td></td><td></td>';
        }

        if ($isASG) {
            return "<td><input type=\"checkbox\" name=\"{$categoryCode}[]\" value=\"{$safeValue}\"></td>";
        }

        return "<td><input type=\"radio\" name=\"{$categoryCode}[1]\" value=\"{$safeValue}\"></td>"
            . "<td><input type=\"radio\" name=\"{$categoryCode}[2]\" value=\"{$safeValue}\"></td>"
            . "<td><input type=\"radio\" name=\"{$categoryCode}[3]\" value=\"{$safeValue}\"></td>";
    }
}
