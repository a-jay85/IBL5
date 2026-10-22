<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftViewInterface;
use UI\TeamCellHelper;
use Security\CsrfGuard;
use Security\HtmlSanitizer;

/**
 * @see DraftViewInterface
 *
 * @phpstan-import-type DraftClassPlayerRow from Contracts\DraftRepositoryInterface
 */
class DraftView implements DraftViewInterface
{
    /**
     * Stat columns rendered after the Team cell, in table order. Every entry is an
     * int-typed key of DraftClassPlayerRow; order must match the <thead> in renderPlayerTable().
     */
    private const STAT_COLUMNS = [
        'age', 'fga', 'fgp', 'fta', 'ftp', 'r_3ga', 'r_3gp', 'orb', 'drb', 'ast', 'stl', 'tvr',
        'blk', 'oo', 'r_drive_off', 'po', 'r_trans_off', 'od', 'dd', 'pd', 'td', 'talent', 'skill', 'intangibles',
    ];

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
    public function renderDraftInterface(array $players, string $teamLogo, ?string $pickOwner, ?int $draftRound, ?int $draftPick, int $seasonYear, int $teamid): string
    {
        $html = '';
        $html .= '<div class="draft-container">';
        $html .= '<h1 class="ibl-title">Draft</h1>';
        $html .= '<img src="images/logo/' . $teamid . '.jpg" alt="Team Logo" class="team-logo-banner">';

        $html .= "<form name='draft_form' action='/ibl5/modules.php?name=Draft&amp;op=select' method='POST'>";
        $html .= CsrfGuard::generateToken('draft_selection');
        $safeTeamLogo = HtmlSanitizer::safeHtmlOutput($teamLogo);
        $html .= "<input type='hidden' name='teamname' value='" . $safeTeamLogo . "'>";
        $html .= "<input type='hidden' name='draft_round' value='$draftRound'>";
        $html .= "<input type='hidden' name='draft_pick' value='$draftPick'>";

        $html .= $this->renderPlayerTable($players, $teamLogo, $pickOwner);
        if ($teamLogo === $pickOwner && $this->hasUndraftedPlayers($players)) {
            $html .= '<div class="draft-submit-container"><button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--lg">Draft Player</button></div>';
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
        $html = '<div class="sticky-scroll-wrapper page-sticky">
        <div class="sticky-scroll-container">
        <table class="sortable ibl-data-table draft-table sticky-table">
            <thead>
                <tr>
                    <th class="sticky-col sticky-corner">Draft</th>
                    <th class="sticky-col-2 sticky-corner">Name</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
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

        $isPickOwner = ($teamLogo === $pickOwner);
        foreach ($players as $player) {
            $html .= $this->renderPlayerRow($player, $isPickOwner);
        }

        $html .= '</tbody></table></div></div>'; // Close table, scroll container, and wrapper

        return $html;
    }

    /**
     * Renders one <tr> for the draft table. Radio input appears only when the viewing
     * team owns the current pick and the player is still undrafted.
     *
     * @param DraftClassPlayerRow $player
     */
    private function renderPlayerRow(array $player, bool $isPickOwner): string
    {
        $isPlayerDrafted = $player['drafted'];
        $playerName = HtmlSanitizer::safeHtmlOutput($player['name']);
        $rowClass = ($isPlayerDrafted !== 0 && $isPlayerDrafted !== null) ? ' class="drafted"' : '';

        $draftCell = '';
        if ($isPickOwner && ($isPlayerDrafted === 0 || $isPlayerDrafted === null)) {
            $draftCell = '<input type="radio" name="player" value="' . HtmlSanitizer::e($player['name']) . '" aria-label="' . $playerName . '">';
        }

        $html = '<tr' . $rowClass . '>
                    <td class="sticky-col">' . $draftCell . '</td>
                    <td class="sticky-col-2">' . $playerName . '</td>';

        $safePos = HtmlSanitizer::safeHtmlOutput($player['pos']);
        $teamTid = $player['team_tid'];
        $html .= '
            <td>' . $safePos . '</td>';

        if ($teamTid !== null) {
            $html .= TeamCellHelper::renderTeamCell(
                $teamTid,
                $player['team'],
                $player['color1'] ?? 'FFFFFF',
                $player['color2'] ?? '000000',
            );
        } else {
            $safeTeam = HtmlSanitizer::safeHtmlOutput($player['team']);
            $html .= '<td>' . $safeTeam . '</td>';
        }

        foreach (self::STAT_COLUMNS as $statKey) {
            $html .= '
            <td>' . (int) $player[$statKey] . '</td>';
        }

        $html .= '
            </tr>';

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
            if ($player['drafted'] === 0 || $player['drafted'] === null) {
                return true;
            }
        }
        return false;
    }
}
