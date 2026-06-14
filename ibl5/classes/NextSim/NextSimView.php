<?php

declare(strict_types=1);

namespace NextSim;

use NextSim\Contracts\NextSimServiceInterface;
use NextSim\Contracts\NextSimViewInterface;
use Player\Player;
use Player\PlayerImageHelper;
use UI\Components\TooltipLabel;
use UI\TableStyles;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * NextSimView - Position-centric HTML rendering for next simulation games
 *
 * Renders a schedule strip followed by five position tables (PG through C),
 * each showing the user's starter vs. all upcoming opponents at that position.
 *
 * @phpstan-import-type NextSimGameData from NextSimServiceInterface
 *
 * @see NextSimViewInterface For the interface contract
 */
class NextSimView implements NextSimViewInterface
{
    private Season $season;

    /** @var array<string, string> Position abbreviation to full name */
    public const POSITION_LABELS = [
        'PG' => 'Point Guards',
        'SG' => 'Shooting Guards',
        'SF' => 'Small Forwards',
        'PF' => 'Power Forwards',
        'C' => 'Centers',
    ];

    public function __construct(Season $season)
    {
        $this->season = $season;
    }

    /**
     * @see NextSimViewInterface::render()
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param array<string, Player> $userStarters User's starting lineup by position
     */
    public function render(array $games, Team $userTeam, array $userStarters): string
    {
        $html = '<div class="next-sim-container">';
        $html .= '<h2 class="ibl-title">Next Sim</h2>';

        if ($games === []) {
            $html .= '<div class="next-sim-empty">No games projected next sim!</div></div>';
            return $html;
        }

        $html .= $this->renderScheduleStrip($games);

        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $html .= $this->renderPositionSection($games, $position, $userTeam, $userStarters);
        }

        $html .= '</div>';
        $html .= $this->renderColumnHighlightScript();

        return $html;
    }

    /**
     * Render the column highlight script for position tables
     *
     * Defines a global `window.IBL_initNextSimHighlight` function so AJAX tab
     * swaps can re-initialize highlights on freshly inserted tables. The
     * function is also auto-called on initial page load.
     */
    public function renderColumnHighlightScript(): string
    {
        return '<script>
window.IBL_initNextSimHighlight=function(root){
    (root||document).querySelectorAll(".next-sim-position-section .team-table").forEach(function(table){
        var tbody=table.querySelector("tbody");
        if(!tbody)return;
        function getHoverColor(row){
            var t=document.createElement("span");
            t.style.cssText="position:absolute;visibility:hidden";
            t.style.backgroundColor=row.classList.contains("next-sim-row--user")
                ?"var(--accent-200,#fed7aa)":"var(--team-row-hover-bg)";
            row.appendChild(t);
            var c=getComputedStyle(t).backgroundColor;
            row.removeChild(t);
            return c;
        }
        function clear(){
            tbody.querySelectorAll(".next-sim-col-hover").forEach(function(c){
                c.classList.remove("next-sim-col-hover");c.style.removeProperty("background-color");
            });
        }
        tbody.addEventListener("mouseover",function(e){
            var td=e.target.closest("td");
            if(!td)return;
            var ci=td.cellIndex;
            clear();
            if(ci<2)return;
            var bg=getHoverColor(td.parentElement);
            tbody.querySelectorAll("tr").forEach(function(row){
                var cell=row.cells[ci];
                if(cell){cell.classList.add("next-sim-col-hover");cell.style.backgroundColor=bg;}
            });
        });
        tbody.addEventListener("mouseleave",clear);
    });
};
window.IBL_initNextSimHighlight();
</script>';
    }

    /**
     * @see NextSimViewInterface::renderScheduleStrip()
     *
     * @param array<int, NextSimGameData> $games Processed game data
     */
    public function renderScheduleStrip(array $games): string
    {
        $html = '<div class="next-sim-schedule-strip">';

        foreach ($games as $gameData) {
            $html .= $this->renderScheduleCard($gameData);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @see NextSimViewInterface::renderTabbedPositionTable()
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param array<string, Player> $userStarters User's starting lineup by position
     */
    public function renderTabbedPositionTable(array $games, string $activePosition, Team $userTeam, array $userStarters): string
    {
        $tabs = self::POSITION_LABELS;
        $apiUrl = 'modules.php?name=DepthChartEntry&op=nextsim-api&teamid=' . $userTeam->teamid;
        $switcher = new \UI\Components\TableViewSwitcher(
            $tabs,
            $activePosition,
            'modules.php?name=NextSim',
            $userTeam->color1,
            $userTeam->color2,
            $apiUrl,
            'closest .nextsim-tab-container',
            'position',
            false
        );
        $tableHtml = $this->renderPositionTable($games, $activePosition, $userTeam, $userStarters);

        return '<div class="next-sim-position-section">' . $switcher->wrap($tableHtml) . '</div>';
    }

    /**
     * Render a single compact schedule card
     *
     * @param NextSimGameData $gameData Game data
     */
    private function renderScheduleCard(array $gameData): string
    {
        $opposingTeam = $gameData['opposingTeam'];
        $opposingTeamId = $opposingTeam->teamid;

        $dayNumberSafe = HtmlSanitizer::safeHtmlOutput((string)$gameData['dayNumber']);
        $locationPrefixSafe = HtmlSanitizer::safeHtmlOutput($gameData['locationPrefix']);
        $seasonRecord = HtmlSanitizer::safeHtmlOutput($opposingTeam->seasonRecord ?? '');
        $gameDate = HtmlSanitizer::safeHtmlOutput($gameData['game']->date);

        $html = '<div class="next-sim-day-row">';
        $html .= '<div class="next-sim-game-date">' . $gameDate . '</div>';
        $html .= '<div class="next-sim-day-label">Day ' . $dayNumberSafe . ' ' . $locationPrefixSafe . '</div>';
        $safeOppTeamName = HtmlSanitizer::safeHtmlOutput($opposingTeam->name ?? '');
        $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamid=' . $opposingTeamId . '" aria-label="' . $safeOppTeamName . '">';
        $html .= '<img src="./images/logo/new' . $opposingTeamId . '.png" alt="" class="next-sim-game-logo" width="20" height="20">';
        $html .= '</a>';
        $html .= '<div class="next-sim-record">' . $seasonRecord;

        if (is_string($gameData['opponentTier']) && $gameData['opponentTier'] !== '') {
            $tier = $gameData['opponentTier'];
            $safeTierLabel = HtmlSanitizer::safeHtmlOutput(ucfirst($tier));
            $html .= '<span class="next-sim-opponent-tier sos-tier--' . $tier . '">' . $safeTierLabel . '</span>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render a position section with heading and ratings table
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param array<string, Player> $userStarters User's starting lineup by position
     */
    private function renderPositionSection(array $games, string $position, Team $userTeam, array $userStarters): string
    {
        $label = self::POSITION_LABELS[$position] ?? $position;
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);

        $html = '<div class="next-sim-position-section">';
        $html .= '<h3 class="ibl-table-title">' . $safeLabel . '</h3>';
        $html .= $this->renderPositionTable($games, $position, $userTeam, $userStarters);
        $html .= '</div>';

        return $html;
    }

    /**
     * @see NextSimViewInterface::renderPositionTable()
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param array<string, Player> $userStarters User's starting lineup by position
     */
    public function renderPositionTable(array $games, string $position, Team $userTeam, array $userStarters): string
    {
        $rows = '';
        if (isset($userStarters[$position])) {
            $rows .= $this->renderPlayerRow($userStarters[$position], $userTeam, null);
        }
        foreach ($games as $gameData) {
            $oppStarter = $gameData['opposingStarters'][$position] ?? null;
            if ($oppStarter instanceof Player) {
                $rows .= $this->renderPlayerRow($oppStarter, $gameData['opposingTeam'], $gameData);
            }
        }

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table" style="<?= TableStyles::inlineTeamVars($userTeam->color1, $userTeam->color2) ?>">
<colgroup span="2"></colgroup><colgroup span="2"></colgroup><colgroup span="6"></colgroup><colgroup span="6"></colgroup><colgroup span="4"></colgroup><colgroup span="4"></colgroup><colgroup span="1"></colgroup>
    <thead>
        <tr>
            <th>Game</th>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th>Age</th>
            <th class="sep-team"><span class="sr-only">Separator</span></th>
            <th>2ga</th>
            <th>2g%</th>
            <th class="sep-team"><span class="sr-only">Separator</span></th>
            <th>fta</th>
            <th>ft%</th>
            <th class="sep-team"><span class="sr-only">Separator</span></th>
            <th>3ga</th>
            <th>3g%</th>
            <th class="sep-team"><span class="sr-only">Separator</span></th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th>foul</th>
            <th class="sep-team"><span class="sr-only">Separator</span></th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th class="sep-team"><span class="sr-only">Separator</span></th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th>td</th>
            <th class="sep-team"><span class="sr-only">Separator</span></th>
            <th>Clu</th>
            <th>Con</th>
            <th class="sep-team"><span class="sr-only">Separator</span></th>
            <th>Days Injured</th>
        </tr>
    </thead>
        <?php
        $header = (string) ob_get_clean();

        return $header . '<tbody>' . $rows . '</tbody></table>';
    }

    /**
     * Render a single player row with per-row team colors
     *
     * @param NextSimGameData|null $gameData Game data for opponent rows, null for user row
     */
    private function renderPlayerRow(Player $player, Team $team, ?array $gameData): string
    {
        $isUserRow = ($gameData === null);
        $rowClass = $isUserRow ? 'next-sim-row--user' : 'next-sim-row--opponent';
        $rowStyle = TableStyles::inlineTeamVars($team->color1, $team->color2);

        $injuryReturnDate = $player->getInjuryReturnDate($this->season->lastSimEndDate);
        $injuryDays = $player->getDaysRemainingForInjury() ?? 0;

        $injuryCell = ($injuryDays > 0 && $injuryReturnDate !== '')
            ? TooltipLabel::render((string) $injuryDays, 'Returns: ' . $injuryReturnDate)
            : (string) $injuryDays;

        $html = '<tr class="' . $rowClass . '" style="' . $rowStyle . '">';
        $html .= $this->renderGameInfoCell($team, $gameData);
        $html .= PlayerImageHelper::renderPlayerCell($player->getPlayerID() ?? 0, $player->getDecoratedName() ?? '', [], $player->getNameStatusClass());
        $html .= '<td>' . HtmlSanitizer::e($player->getPosition() ?? '') . '</td>';
        $html .= '<td>' . (int) $player->getAge() . '</td>';
        $html .= '<td class="sep-team"></td>';
        $html .= '<td>' . (int) $player->getRatingFieldGoalAttempts() . '</td>';
        $html .= '<td>' . (int) $player->getRatingFieldGoalPercentage() . '</td>';
        $html .= '<td class="sep-weak"></td>';
        $html .= '<td>' . (int) $player->getRatingFreeThrowAttempts() . '</td>';
        $html .= '<td>' . (int) $player->getRatingFreeThrowPercentage() . '</td>';
        $html .= '<td class="sep-weak"></td>';
        $html .= '<td>' . (int) $player->getRatingThreePointAttempts() . '</td>';
        $html .= '<td>' . (int) $player->getRatingThreePointPercentage() . '</td>';
        $html .= '<td class="sep-team"></td>';
        $html .= '<td>' . (int) $player->getRatingOffensiveRebounds() . '</td>';
        $html .= '<td>' . (int) $player->getRatingDefensiveRebounds() . '</td>';
        $html .= '<td>' . (int) $player->getRatingAssists() . '</td>';
        $html .= '<td>' . (int) $player->getRatingSteals() . '</td>';
        $html .= '<td>' . (int) $player->getRatingTurnovers() . '</td>';
        $html .= '<td>' . (int) $player->getRatingBlocks() . '</td>';
        $html .= '<td>' . (int) $player->getRatingFouls() . '</td>';
        $html .= '<td class="sep-team"></td>';
        $html .= '<td>' . (int) $player->getRatingOutsideOffense() . '</td>';
        $html .= '<td>' . (int) $player->getRatingDriveOffense() . '</td>';
        $html .= '<td>' . (int) $player->getRatingPostOffense() . '</td>';
        $html .= '<td>' . (int) $player->getRatingTransitionOffense() . '</td>';
        $html .= '<td class="sep-weak"></td>';
        $html .= '<td>' . (int) $player->getRatingOutsideDefense() . '</td>';
        $html .= '<td>' . (int) $player->getRatingDriveDefense() . '</td>';
        $html .= '<td>' . (int) $player->getRatingPostDefense() . '</td>';
        $html .= '<td>' . (int) $player->getRatingTransitionDefense() . '</td>';
        $html .= '<td class="sep-team"></td>';
        $html .= '<td>' . (int) $player->getRatingClutch() . '</td>';
        $html .= '<td>' . (int) $player->getRatingConsistency() . '</td>';
        $html .= '<td class="sep-team"></td>';
        $html .= '<td>' . $injuryCell . '</td>';
        $html .= '</tr>';

        return $html;
    }

    /**
     * Render the Game info cell
     *
     * For user row: team logo + name with orange highlight.
     * For opponent rows: "Day N @/vs" + small team logo.
     *
     * @param NextSimGameData|null $gameData Null for user row
     */
    private function renderGameInfoCell(Team $team, ?array $gameData): string
    {
        $teamId = $team->teamid;

        $teamLink = 'modules.php?name=Team&amp;op=team&amp;teamid=' . $teamId;
        $logoImg = '<img src="./images/logo/new' . $teamId . '.png" alt="" class="next-sim-game-logo" width="20" height="20">';

        $safeTeamName = HtmlSanitizer::safeHtmlOutput($team->name ?? '');

        if ($gameData === null) {
            // User row: team icon

            return '<td class="next-sim-game-info-cell"><span>'
                . '<a href="' . $teamLink . '" aria-label="' . $safeTeamName . '">' . $logoImg . '</a>'
                . '</span></td>';
        }

        // Opponent row: @/vs + team logo
        $locationPrefixSafe = HtmlSanitizer::safeHtmlOutput($gameData['locationPrefix']);

        return '<td class="next-sim-game-info-cell"><span>'
            . $locationPrefixSafe . ' '
            . '<a href="' . $teamLink . '" aria-label="' . $safeTeamName . '">' . $logoImg . '</a>'
            . '</span></td>';
    }
}
