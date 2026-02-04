<?php

declare(strict_types=1);

namespace OneOnOneGame;

use OneOnOneGame\Contracts\OneOnOneGameRepositoryInterface;
use OneOnOneGame\Contracts\OneOnOneGameViewInterface;
use Utilities\HtmlSanitizer;

/**
 * OneOnOneGameView - Renders HTML for the One-on-One game module
 *
 * Uses the IBL5 design system with ibl-filter-form for player selection
 * and ibl-data-table for stats display.
 *
 * @see OneOnOneGameViewInterface For method contracts
 * @phpstan-import-type GameRecord from OneOnOneGameRepositoryInterface
 */
class OneOnOneGameView implements OneOnOneGameViewInterface
{
    /**
     * @see OneOnOneGameViewInterface::renderHeader()
     */
    public function renderHeader(): string
    {
        return '<h2 class="ibl-title">One-on-One Match</h2>';
    }

    /**
     * @see OneOnOneGameViewInterface::renderPlayerSelectionForm()
     */
    public function renderPlayerSelectionForm(array $players, ?int $selectedPlayer1, ?int $selectedPlayer2): string
    {
        $html = '<form name="OneOnOneGame" method="post" action="modules.php?name=OneOnOneGame" class="ibl-filter-form">' . "\n";
        $html .= '<div class="ibl-filter-form__row">' . "\n";

        $html .= '<div class="ibl-filter-form__group">' . "\n";
        $html .= '<label class="ibl-filter-form__label" for="pid1">Player One</label>' . "\n";
        $html .= '<select name="pid1" id="pid1" class="ibl-select">' . "\n";

        foreach ($players as $player) {
            $pid = $player['pid'];
            /** @var string $name */
            $name = HtmlSanitizer::safeHtmlOutput($player['name']);
            $selected = ($pid === $selectedPlayer1) ? ' selected' : '';
            $html .= '<option value="' . $pid . '"' . $selected . '>' . $name . '</option>' . "\n";
        }

        $html .= '</select>' . "\n";
        $html .= '</div>' . "\n";

        $html .= '<div class="ibl-filter-form__group">' . "\n";
        $html .= '<label class="ibl-filter-form__label" for="pid2">Player Two</label>' . "\n";
        $html .= '<select name="pid2" id="pid2" class="ibl-select">' . "\n";

        foreach ($players as $player) {
            $pid = $player['pid'];
            /** @var string $name */
            $name = HtmlSanitizer::safeHtmlOutput($player['name']);
            $selected = ($pid === $selectedPlayer2) ? ' selected' : '';
            $html .= '<option value="' . $pid . '"' . $selected . '>' . $name . '</option>' . "\n";
        }

        $html .= '</select>' . "\n";
        $html .= '</div>' . "\n";

        $html .= '<button type="submit" class="ibl-filter-form__submit">Begin One-on-One Match</button>' . "\n";

        $html .= '</div>' . "\n";
        $html .= '</form>' . "\n";

        return $html;
    }

    /**
     * @see OneOnOneGameViewInterface::renderGameLookupForm()
     */
    public function renderGameLookupForm(): string
    {
        return '<form name="LookUpOldGame" method="post" action="modules.php?name=OneOnOneGame" class="ibl-filter-form">' . "\n"
            . '<div class="ibl-filter-form__row">' . "\n"
            . '<div class="ibl-filter-form__group">' . "\n"
            . '<label class="ibl-filter-form__label" for="gameid">Review Old Game (Game ID)</label>' . "\n"
            . '<input type="text" name="gameid" id="gameid" class="ibl-input ibl-input--sm">' . "\n"
            . '</div>' . "\n"
            . '<button type="submit" class="ibl-filter-form__submit">Review Old Game</button>' . "\n"
            . '</div>' . "\n"
            . '</form>' . "\n";
    }

    /**
     * @see OneOnOneGameViewInterface::renderErrors()
     */
    public function renderErrors(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        $html = '<div class="ibl-alert ibl-alert--error">';
        foreach ($errors as $error) {
            /** @var string $sanitizedError */
            $sanitizedError = HtmlSanitizer::safeHtmlOutput($error);
            $html .= $sanitizedError . "<br>\n";
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @see OneOnOneGameViewInterface::renderGameResult()
     */
    public function renderGameResult(OneOnOneGameResult $result, int $gameId): string
    {
        $html = '<div class="ibl-card"><div class="ibl-card__body">';
        $html .= $result->playByPlay;
        $html .= '<strong style="font-weight: bold;">GAME ID: ' . $gameId . '</strong>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * @see OneOnOneGameViewInterface::renderGameReplay()
     *
     * @param GameRecord $gameData
     */
    public function renderGameReplay(array $gameData): string
    {
        $gameId = $gameData['gameid'];
        /** @var string $winner */
        $winner = HtmlSanitizer::safeHtmlOutput($gameData['winner']);
        /** @var string $loser */
        $loser = HtmlSanitizer::safeHtmlOutput($gameData['loser']);
        $winScore = $gameData['winscore'];
        $lossScore = $gameData['lossscore'];
        /** @var string $owner */
        $owner = HtmlSanitizer::safeHtmlOutput($gameData['owner']);
        // Play-by-play is already sanitized when generated, don't double-escape
        $playByPlay = $gameData['playbyplay'];

        return '<div class="ibl-card">'
            . '<div class="ibl-card__header"><h2 class="ibl-card__title">Replay of Game Number ' . $gameId . '</h2></div>'
            . '<div class="ibl-card__body">'
            . '<div style="text-align: center; margin-bottom: 1rem;">'
            . '<strong style="font-weight: bold;">' . $winner . ' ' . $winScore . ', ' . $loser . ' ' . $lossScore . '</strong><br>'
            . '<small>(Game played by ' . $owner . ')</small>'
            . '</div>'
            . $playByPlay
            . '</div></div>';
    }
}
