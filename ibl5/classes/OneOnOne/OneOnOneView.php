<?php

declare(strict_types=1);

namespace OneOnOne;

use OneOnOne\Contracts\OneOnOneViewInterface;
use Utilities\HtmlSanitizer;

/**
 * OneOnOneView - Renders HTML for the One-on-One game module
 * 
 * @see OneOnOneViewInterface For method contracts
 */
class OneOnOneView implements OneOnOneViewInterface
{
    /**
     * @see OneOnOneViewInterface::renderHeader()
     */
    public function renderHeader(): string
    {
        return '<div style="text-align: center;"><table><tr><th>One-on-One Match</th></tr></table></div>';
    }

    /**
     * @see OneOnOneViewInterface::renderPlayerSelectionForm()
     */
    public function renderPlayerSelectionForm(array $players, ?int $selectedPlayer1, ?int $selectedPlayer2): string
    {
        $html = '<form name="OneOnOne" method="post" action="modules.php?name=One-on-One">' . "\n";
        $html .= 'Player One: <select name="pid1">' . "\n";
        
        foreach ($players as $player) {
            $pid = (int) $player['pid'];
            $name = HtmlSanitizer::safeHtmlOutput($player['name']);
            $selected = ($pid === $selectedPlayer1) ? ' selected' : '';
            $html .= "<option value=\"$pid\"$selected>$name</option>\n";
        }
        
        $html .= '</select> | Player Two: <select name="pid2">' . "\n";
        
        foreach ($players as $player) {
            $pid = (int) $player['pid'];
            $name = HtmlSanitizer::safeHtmlOutput($player['name']);
            $selected = ($pid === $selectedPlayer2) ? ' selected' : '';
            $html .= "<option value=\"$pid\"$selected>$name</option>\n";
        }
        
        $html .= '</select><input type="submit" value="Begin One-on-One Match"></form>' . "\n";
        
        return $html;
    }

    /**
     * @see OneOnOneViewInterface::renderGameLookupForm()
     */
    public function renderGameLookupForm(): string
    {
        return '<form name="LookUpOldGame" method="post" action="modules.php?name=One-on-One">
Review Old Game (Input Game ID): <input type="text" name="gameid" size="11"><input type="submit" value="Review Old Game">
</form>
<hr>';
    }

    /**
     * @see OneOnOneViewInterface::renderErrors()
     */
    public function renderErrors(array $errors): string
    {
        if (empty($errors)) {
            return '';
        }
        
        $html = '';
        foreach ($errors as $error) {
            $html .= HtmlSanitizer::safeHtmlOutput($error) . "<br>\n";
        }
        
        return $html;
    }

    /**
     * @see OneOnOneViewInterface::renderGameResult()
     */
    public function renderGameResult(OneOnOneGameResult $result, int $gameId): string
    {
        $html = $result->playByPlay;
        $html .= "GAME ID: $gameId";
        
        return $html;
    }

    /**
     * @see OneOnOneViewInterface::renderGameReplay()
     */
    public function renderGameReplay(array $gameData): string
    {
        $gameId = (int) $gameData['gameid'];
        $winner = HtmlSanitizer::safeHtmlOutput($gameData['winner']);
        $loser = HtmlSanitizer::safeHtmlOutput($gameData['loser']);
        $winScore = (int) $gameData['winscore'];
        $lossScore = (int) $gameData['lossscore'];
        $owner = HtmlSanitizer::safeHtmlOutput($gameData['owner']);
        $playByPlay = $gameData['playbyplay'];
        
        return '<div style="text-align: center;"><h2>Replay of Game Number ' . $gameId . '<br>'
            . $winner . ' ' . $winScore . ', ' . $loser . ' ' . $lossScore . '<br>'
            . '<small>(Game played by ' . $owner . ')</small></h2></div> '
            . $playByPlay;
    }
}
