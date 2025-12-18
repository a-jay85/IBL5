<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftProcessorInterface;

/**
 * @see DraftProcessorInterface
 */
class DraftProcessor implements DraftProcessorInterface
{
    // Configuration constants
    const DRAFT_MODULE_URL = 'https://www.iblhoops.net/ibl5/modules.php?name=Draft';
    const ADMIN_CONTACT = 'the administrator';

    /**
     * @see DraftProcessorInterface::createDraftAnnouncement()
     */
    public function createDraftAnnouncement(int $draftPick, int $draftRound, int $seasonYear, string $teamName, string $playerName): string
    {
        return "With pick #$draftPick in round $draftRound of the $seasonYear IBL Draft, the **" 
            . $teamName . "** select **" . $playerName . "!**";
    }

    /**
     * @see DraftProcessorInterface::createNextTeamMessage()
     */
    public function createNextTeamMessage(string $baseMessage, ?int $discordID, ?int $seasonYear): string
    {
        if ($discordID !== null) {
            return $baseMessage . '
    **<@!' . $discordID . '>** is on the clock!
' . self::DRAFT_MODULE_URL;
        } else {
            return $baseMessage . "
    **🏁 __The $seasonYear IBL Draft has officially concluded!__ 🏁**";
        }
    }

    /**
     * @see DraftProcessorInterface::getSuccessMessage()
     */
    public function getSuccessMessage(string $message): string
    {
        return "$message<p>
        <a href=\"/ibl5/modules.php?name=Draft\">Go back to the Draft module</a>";
    }

    /**
     * @see DraftProcessorInterface::getDatabaseErrorMessage()
     */
    public function getDatabaseErrorMessage(): string
    {
        return "Oops, something went wrong, and at least one of the draft database tables wasn't updated.<p>
            Let " . self::ADMIN_CONTACT . " know what happened and they'll look into it.<p>
            
            <a href=\"/ibl5/modules.php?name=Draft\">Go back to the Draft module</a>";
    }
}
