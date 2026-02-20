<?php

declare(strict_types=1);

namespace NextSim;

use NextSim\Contracts\NextSimServiceInterface;
use NextSim\Contracts\NextSimViewInterface;
use Player\Player;
use Player\PlayerImageHelper;
use UI\Components\TooltipLabel;
use UI\TableStyles;
use Utilities\HtmlSanitizer;

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
    private \Season $season;

    /** @var array<string, string> Position abbreviation to full name */
    private const POSITION_LABELS = [
        'PG' => 'Point Guards',
        'SG' => 'Shooting Guards',
        'SF' => 'Small Forwards',
        'PF' => 'Power Forwards',
        'C' => 'Centers',
    ];

    public function __construct(\Season $season)
    {
        $this->season = $season;
    }

    /**
     * @see NextSimViewInterface::render()
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param array<string, Player> $userStarters User's starting lineup by position
     */
    public function render(array $games, \Team $userTeam, array $userStarters): string
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

        return $html;
    }

    /**
     * Render the horizontal schedule strip of compact game cards
     *
     * @param array<int, NextSimGameData> $games Processed game data
     */
    private function renderScheduleStrip(array $games): string
    {
        $html = '<div class="next-sim-schedule-strip">';

        foreach ($games as $gameData) {
            $html .= $this->renderScheduleCard($gameData);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a single compact schedule card
     *
     * @param NextSimGameData $gameData Game data
     */
    private function renderScheduleCard(array $gameData): string
    {
        $opposingTeam = $gameData['opposingTeam'];
        $opposingTeamId = $opposingTeam->teamID;

        /** @var string $dayNumberSafe */
        $dayNumberSafe = HtmlSanitizer::safeHtmlOutput((string)$gameData['dayNumber']);
        /** @var string $locationPrefixSafe */
        $locationPrefixSafe = HtmlSanitizer::safeHtmlOutput($gameData['locationPrefix']);
        /** @var string $seasonRecord */
        $seasonRecord = HtmlSanitizer::safeHtmlOutput($opposingTeam->seasonRecord ?? '');
        /** @var string $gameDate */
        $gameDate = HtmlSanitizer::safeHtmlOutput($gameData['game']->date);

        $html = '<div class="next-sim-day-row">';
        $html .= '<div class="next-sim-game-date">' . $gameDate . '</div>';
        $html .= '<div class="next-sim-day-label">Day ' . $dayNumberSafe . ' ' . $locationPrefixSafe . '</div>';
        $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $opposingTeamId . '">';
        $html .= '<img src="./images/logo/new' . $opposingTeamId . '.png" alt="" class="next-sim-game-logo" width="20" height="20">';
        $html .= '</a>';
        $html .= '<div class="next-sim-record">' . $seasonRecord;

        if (is_string($gameData['opponentTier']) && $gameData['opponentTier'] !== '') {
            $tier = $gameData['opponentTier'];
            /** @var string $safeTierLabel */
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
    private function renderPositionSection(array $games, string $position, \Team $userTeam, array $userStarters): string
    {
        $label = self::POSITION_LABELS[$position] ?? $position;
        /** @var string $safeLabel */
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);

        $html = '<div class="next-sim-position-section">';
        $html .= '<h3 class="ibl-table-title">' . $safeLabel . '</h3>';
        $html .= $this->renderPositionTable($games, $position, $userTeam, $userStarters);
        $html .= '</div>';

        return $html;
    }

    /**
     * Render the full ratings table for a single position
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param array<string, Player> $userStarters User's starting lineup by position
     */
    private function renderPositionTable(array $games, string $position, \Team $userTeam, array $userStarters): string
    {
        $tableStyle = TableStyles::inlineVars($userTeam->color1, $userTeam->color2);

        ob_start();
        ?>
<table class="ibl-data-table team-table responsive-table" style="<?= $tableStyle ?>">
<colgroup span="2"></colgroup><colgroup span="2"></colgroup><colgroup span="6"></colgroup><colgroup span="6"></colgroup><colgroup span="4"></colgroup><colgroup span="4"></colgroup><colgroup span="1"></colgroup>
    <thead>
        <tr>
            <th>Game</th>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th>Age</th>
            <th class="sep-team"></th>
            <th>2ga</th>
            <th>2g%</th>
            <th class="sep-team"></th>
            <th>fta</th>
            <th>ft%</th>
            <th class="sep-team"></th>
            <th>3ga</th>
            <th>3g%</th>
            <th class="sep-team"></th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th>foul</th>
            <th class="sep-team"></th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th class="sep-team"></th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th>td</th>
            <th class="sep-team"></th>
            <th>Clu</th>
            <th>Con</th>
            <th class="sep-team"></th>
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
<?php
        // User's starter row
        if (isset($userStarters[$position])) {
            echo $this->renderPlayerRow($userStarters[$position], $userTeam, null);
        }

        // Opponent rows in chronological order
        foreach ($games as $gameData) {
            $oppStarter = $gameData['opposingStarters'][$position] ?? null;
            if ($oppStarter instanceof Player) {
                echo $this->renderPlayerRow($oppStarter, $gameData['opposingTeam'], $gameData);
            }
        }
?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a single player row with per-row team colors
     *
     * @param NextSimGameData|null $gameData Game data for opponent rows, null for user row
     */
    private function renderPlayerRow(Player $player, \Team $team, ?array $gameData): string
    {
        $isUserRow = ($gameData === null);
        $rowClass = $isUserRow ? 'next-sim-row--user' : 'next-sim-row--opponent';
        $rowStyle = TableStyles::inlineVars($team->color1, $team->color2);

        $injuryReturnDate = $player->getInjuryReturnDate($this->season->lastSimEndDate);
        $injuryDays = $player->daysRemainingForInjury ?? 0;

        $gameInfoCell = $this->renderGameInfoCell($team, $gameData);

        ob_start();
        ?>
        <tr class="<?= $rowClass ?>" style="<?= $rowStyle ?>">
            <?= $gameInfoCell ?>
            <?= PlayerImageHelper::renderPlayerCell($player->playerID ?? 0, $player->decoratedName ?? '') ?>
            <td style="text-align: center;"><?= htmlspecialchars($player->position ?? '') ?></td>
            <td style="text-align: center;"><?= (int)$player->age ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->ratingFieldGoalAttempts ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingFieldGoalPercentage ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= (int)$player->ratingFreeThrowAttempts ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingFreeThrowPercentage ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= (int)$player->ratingThreePointAttempts ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingThreePointPercentage ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->ratingOffensiveRebounds ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingDefensiveRebounds ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingAssists ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingSteals ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingTurnovers ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingBlocks ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingFouls ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->ratingOutsideOffense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingDriveOffense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingPostOffense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingTransitionOffense ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= (int)$player->ratingOutsideDefense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingDriveDefense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingPostDefense ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingTransitionDefense ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= (int)$player->ratingClutch ?></td>
            <td style="text-align: center;"><?= (int)$player->ratingConsistency ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= ($injuryDays > 0 && $injuryReturnDate !== '')
                ? TooltipLabel::render((string) $injuryDays, 'Returns: ' . $injuryReturnDate)
                : (string) $injuryDays ?></td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the Game info cell
     *
     * For user row: team logo + name with orange highlight.
     * For opponent rows: "Day N @/vs" + small team logo.
     *
     * @param NextSimGameData|null $gameData Null for user row
     */
    private function renderGameInfoCell(\Team $team, ?array $gameData): string
    {
        $teamId = $team->teamID;

        $teamLink = 'modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId;
        $logoImg = '<img src="./images/logo/new' . $teamId . '.png" alt="" class="next-sim-game-logo" width="20" height="20">';

        if ($gameData === null) {
            // User row: team icon

            return '<td class="next-sim-game-info-cell"><span>'
                . '<a href="' . $teamLink . '">' . $logoImg . '</a>'
                . '</span></td>';
        }

        // Opponent row: @/vs + team logo
        /** @var string $locationPrefixSafe */
        $locationPrefixSafe = HtmlSanitizer::safeHtmlOutput($gameData['locationPrefix']);

        return '<td class="next-sim-game-info-cell"><span>'
            . $locationPrefixSafe . ' '
            . '<a href="' . $teamLink . '">' . $logoImg . '</a>'
            . '</span></td>';
    }
}
