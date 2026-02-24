<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftViewInterface;
use Utilities\HtmlSanitizer;

/**
 * @see DraftViewInterface
 *
 * @phpstan-import-type DraftClassPlayerRow from Contracts\DraftRepositoryInterface
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
     *
     * @param list<DraftClassPlayerRow> $players
     */
    public function renderDraftInterface(array $players, string $teamLogo, ?string $pickOwner, ?int $draftRound, ?int $draftPick, int $seasonYear, int $tid): string
    {
        $html = '';
        $html .= '<div class="draft-container">';
        $html .= '<h2 class="ibl-title">Draft</h2>';
        $html .= '<img src="images/logo/' . $tid . '.jpg" alt="Team Logo" class="team-logo-banner">';

        $html .= "<form name='draft_form' action='/ibl5/modules/Draft/draft_selection.php' method='POST'>";
        $safeTeamLogo = HtmlSanitizer::safeHtmlOutput($teamLogo);
        $html .= "<input type='hidden' name='teamname' value='" . $safeTeamLogo . "'>";
        $html .= "<input type='hidden' name='draft_round' value='$draftRound'>";
        $html .= "<input type='hidden' name='draft_pick' value='$draftPick'>";

        $html .= $this->renderPlayerTable($players, $teamLogo, $pickOwner);
        if ($teamLogo === $pickOwner && $this->hasUndraftedPlayers($players)) {
            $html .= '<div class="draft-submit-container"><button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--lg" onclick="this.disabled=true;this.textContent=\'Submitting...\'; this.form.submit();">Draft Player</button></div>';
        }

        $html .= "</form></div>";

        return $html;
    }

    /**
     * @see DraftViewInterface::renderPlayerTable()
     *
     * @param list<DraftClassPlayerRow> $players
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
            $rowClass = ($isPlayerDrafted !== 0 && $isPlayerDrafted !== null) ? ' class="drafted"' : '';

            if ($teamLogo === $pickOwner && $isPlayerDrafted === 0) {
                $html .= '<tr' . $rowClass . '>
                    <td class="sticky-col"><input type="radio" name="player" value="' . htmlspecialchars($player['name'], ENT_QUOTES) . '"></td>
                    <td class="sticky-col-2" style="white-space: nowrap;">' . $playerName . '</td>';
            } elseif ($isPlayerDrafted === 1) {
                $html .= '<tr' . $rowClass . '>
                    <td class="sticky-col"></td>
                    <td class="sticky-col-2" style="white-space: nowrap;">' . $playerName . '</td>';
            } else {
                $html .= '<tr' . $rowClass . '>
                    <td class="sticky-col"></td>
                    <td class="sticky-col-2" style="white-space: nowrap;">' . $playerName . '</td>';
            }

            $safePos = HtmlSanitizer::safeHtmlOutput($player['pos']);
            $safeTeam = HtmlSanitizer::safeHtmlOutput($player['team']);
            $html .= '
            <td>' . $safePos . '</td>
            <td>' . $safeTeam . '</td>
            <td>' . (int) $player['age'] . '</td>
            <td>' . (int) $player['fga'] . '</td>
            <td>' . (int) $player['fgp'] . '</td>
            <td>' . (int) $player['fta'] . '</td>
            <td>' . (int) $player['ftp'] . '</td>
            <td>' . (int) $player['tga'] . '</td>
            <td>' . (int) $player['tgp'] . '</td>
            <td>' . (int) $player['orb'] . '</td>
            <td>' . (int) $player['drb'] . '</td>
            <td>' . (int) $player['ast'] . '</td>
            <td>' . (int) $player['stl'] . '</td>
            <td>' . (int) $player['tvr'] . '</td>
            <td>' . (int) $player['blk'] . '</td>
            <td>' . (int) $player['oo'] . '</td>
            <td>' . (int) $player['do'] . '</td>
            <td>' . (int) $player['po'] . '</td>
            <td>' . (int) $player['to'] . '</td>
            <td>' . (int) $player['od'] . '</td>
            <td>' . (int) $player['dd'] . '</td>
            <td>' . (int) $player['pd'] . '</td>
            <td>' . (int) $player['td'] . '</td>
            <td>' . (int) $player['talent'] . '</td>
            <td>' . (int) $player['skill'] . '</td>
            <td>' . (int) $player['intangibles'] . '</td>
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
     *
     * @param list<DraftClassPlayerRow> $players
     */
    public function hasUndraftedPlayers(array $players): bool
    {
        foreach ($players as $player) {
            if ($player['drafted'] === 0) {
                return true;
            }
        }
        return false;
    }
}
