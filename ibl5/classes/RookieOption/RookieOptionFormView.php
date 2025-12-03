<?php

namespace RookieOption;

use Services\DatabaseService;
use RookieOption\Contracts\RookieOptionFormViewInterface;

/**
 * @see RookieOptionFormViewInterface
 */
class RookieOptionFormView implements RookieOptionFormViewInterface
{
    /**
     * @see RookieOptionFormViewInterface::renderError()
     */
    public function renderError(string $errorMessage): void
    {
        $errorMessageEscaped = nl2br(DatabaseService::safeHtmlOutput($errorMessage));
        
        echo "{$errorMessageEscaped}<p>
            <a href=\"javascript:history.back()\">Go Back</a>";
    }
    
    /**
     * @see RookieOptionFormViewInterface::renderForm()
     */
    public function renderForm($player, string $teamName, int $rookieOptionValue): void
    {
        $playerID = (int) $player->playerID;
        $playerPosition = DatabaseService::safeHtmlOutput($player->position);
        $playerName = DatabaseService::safeHtmlOutput($player->name);
        $teamNameEscaped = DatabaseService::safeHtmlOutput($teamName);
        $rookieOptionValueEscaped = DatabaseService::safeHtmlOutput((string) $rookieOptionValue);
        $playerImageUrl = \Player\PlayerImageHelper::getImageUrl($playerID);
        
        echo "<img align=left src=\"{$playerImageUrl}\"><p>
        You may exercise the rookie option on <b>{$playerPosition} {$playerName}</b>.<p>
        Their contract value the season after this one will be <b>{$rookieOptionValueEscaped}</b>.<p>
        WARNING: By exercising this option, <b>you can't use an in-season contract extension on them next season</b>.<p>
        <b>They will become a free agent</b>.<p>
        <form name=\"RookieExtend\" method=\"post\" action=\"/ibl5/modules/Player/rookieoption.php\">
            <input type=\"hidden\" name=\"teamname\" value=\"{$teamNameEscaped}\">
            <input type=\"hidden\" name=\"playerID\" value=\"{$playerID}\">
            <input type=\"hidden\" name=\"rookieOptionValue\" value=\"{$rookieOptionValueEscaped}\">
            <input type=\"submit\" value=\"Exercise Rookie Option\">
        </form>";
    }
}
