<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftViewInterface;
use Services\DatabaseService;
use Utilities\HtmlSanitizer;

/**
 * @see DraftViewInterface
 */
class DraftView implements DraftViewInterface
{
    /**
     * @see DraftViewInterface::renderValidationError()
     */
    public function renderValidationError(string $errorMessage): string
    {
        $errorMessage = HtmlSanitizer::safeHtmlOutput($errorMessage);
        $retryInstructions = $this->getRetryInstructions($errorMessage);

        return '<div class="draft-error">
            <p>Oops, ' . $errorMessage . '</p>
            <p><a href="/ibl5/modules.php?name=Draft">Click here to return to the Draft module</a>' . $retryInstructions . '</p>
        </div>';
    }

    /**
     * @see DraftViewInterface::renderDraftInterface()
     */
    public function renderDraftInterface(array $players, string $teamLogo, ?string $pickOwner, ?int $draftRound, ?int $draftPick, int $seasonYear, int $tid): string
    {
        $html = $this->getStyleBlock();
        $html .= '<div class="draft-container">';
        $html .= '<div class="draft-header">';
        $html .= '<img src="images/logo/' . $tid . '.jpg" alt="Team Logo" class="draft-team-logo">';
        $html .= '<h2 class="draft-title">Welcome to the ' . HtmlSanitizer::safeHtmlOutput((string)$seasonYear) . ' IBL Draft!</h2>';
        $html .= '</div>';

        $html .= "<form name='draft_form' action='/ibl5/modules/Draft/draft_selection.php' method='POST'>";
        $html .= "<input type='hidden' name='teamname' value='" . HtmlSanitizer::safeHtmlOutput($teamLogo) . "'>";
        $html .= "<input type='hidden' name='draft_round' value='$draftRound'>";
        $html .= "<input type='hidden' name='draft_pick' value='$draftPick'>";

        $html .= $this->renderPlayerTable($players, $teamLogo, $pickOwner);
        if ($teamLogo == $pickOwner && $this->hasUndraftedPlayers($players)) {
            $html .= '<div class="draft-submit-container"><button type="submit" class="draft-submit-btn" onclick="this.disabled=true;this.textContent=\'Submitting...\'; this.form.submit();">Draft Player</button></div>';
        }

        $html .= "</form></div>";

        return $html;
    }

    /**
     * Generate CSS styles for the draft interface
     *
     * Uses consolidated .ibl-data-table from design system with draft-specific overrides.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
/* Draft-specific styles only - table base comes from .ibl-data-table */
.draft-container {
    max-width: 100%;
    margin: 0 auto;
}
.draft-header {
    text-align: center;
    margin-bottom: 1.5rem;
}
.draft-team-logo {
    width: 80px;
    height: 80px;
    object-fit: contain;
    margin-bottom: 0.75rem;
}
.draft-title {
    font-family: var(--font-display, \'Barlow Condensed\', sans-serif);
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--navy-900, #0f172a);
    margin: 0;
}
.draft-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--radius-md, 0.375rem);
    padding: 1rem;
    color: #991b1b;
    margin-bottom: 1rem;
}
.draft-error a {
    color: #991b1b;
    font-weight: 500;
}
.draft-table input[type="radio"] {
    width: 1rem;
    height: 1rem;
    accent-color: var(--accent-500, #f97316);
}
.draft-submit-container {
    text-align: center;
    margin-top: 1rem;
}
.draft-submit-btn {
    font-family: var(--font-display, \'Barlow Condensed\', sans-serif);
    font-size: 1.125rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--accent-500, #f97316), var(--accent-600, #ea580c));
    color: white;
    border: none;
    border-radius: var(--radius-md, 0.375rem);
    cursor: pointer;
    transition: transform 150ms ease, box-shadow 150ms ease;
}
.draft-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg, 0 10px 15px -3px rgb(0 0 0 / 0.1));
}
.draft-submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
</style>';
    }

    /**
     * @see DraftViewInterface::renderPlayerTable()
     */
    public function renderPlayerTable(array $players, string $teamLogo, ?string $pickOwner): string
    {
        $html = '<div class="table-scroll-container">
        <table class="sortable ibl-data-table draft-table responsive-table">
            <thead>
                <tr>
                    <th class="sticky-col">Draft</th>
                    <th class="sticky-col-2">Name</th>
                    <th>Pos</th>
                    <th>Team</th>
                    <th>Age</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th>tga</th>
                    <th>tgp</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($players as $player) {
            $isPlayerDrafted = $player['drafted'];
            $playerName = HtmlSanitizer::safeHtmlOutput($player['name']);
            $rowClass = $isPlayerDrafted ? ' class="drafted"' : '';

            if ($teamLogo == $pickOwner && $isPlayerDrafted == 0) {
                $html .= '<tr' . $rowClass . '>
                    <td class="sticky-col"><input type="radio" name="player" value="' . htmlspecialchars($player['name'], ENT_QUOTES) . '"></td>
                    <td class="sticky-col-2" style="white-space: nowrap;">' . $playerName . '</td>';
            } elseif ($isPlayerDrafted == 1) {
                $html .= '<tr' . $rowClass . '>
                    <td class="sticky-col"></td>
                    <td class="sticky-col-2" style="white-space: nowrap;">' . $playerName . '</td>';
            } else {
                $html .= '<tr' . $rowClass . '>
                    <td class="sticky-col"></td>
                    <td class="sticky-col-2" style="white-space: nowrap;">' . $playerName . '</td>';
            }

            $html .= '
            <td>' . HtmlSanitizer::safeHtmlOutput($player['pos']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['team']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['age']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['fga']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['fgp']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['fta']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['ftp']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['tga']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['tgp']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['orb']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['drb']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['ast']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['stl']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['tvr']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['blk']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['oo']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['do']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['po']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['to']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['od']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['dd']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['pd']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['td']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['talent']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['skill']) . '</td>
            <td>' . HtmlSanitizer::safeHtmlOutput($player['intangibles']) . '</td>
            </tr>';
        }

        $html .= '</tbody></table></div>'; // Close table and scroll container

        return $html;
    }

    /**
     * @see DraftViewInterface::getRetryInstructions()
     */
    public function getRetryInstructions(string $errorMessage): string
    {
        if (strpos($errorMessage, "didn't select") !== false) {
            return " and please select a player before hitting the Draft button.";
        }

        return " and if it's your turn, try drafting again.";
    }

    /**
     * @see DraftViewInterface::hasUndraftedPlayers()
     */
    public function hasUndraftedPlayers(array $players): bool
    {
        foreach ($players as $player) {
            if ($player['drafted'] == 0) {
                return true;
            }
        }
        return false;
    }
}
