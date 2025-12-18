<?php

declare(strict_types=1);

namespace Draft\Contracts;

/**
 * DraftProcessorInterface - Contract for draft business logic
 *
 * Handles draft announcement messages, Discord notifications, and
 * user-facing message formatting.
 */
interface DraftProcessorInterface
{
    /**
     * Create a draft announcement message
     *
     * Formats the announcement for a player being drafted. Used in both
     * Discord notifications and HTML success messages.
     *
     * @param int $draftPick The overall pick number (1-indexed across all rounds)
     * @param int $draftRound The round number
     * @param string $seasonYear The year of the draft (e.g., '2026')
     * @param string $teamName The name of the team making the selection
     * @param string $playerName The name of the drafted player
     * @return string The formatted announcement message
     *
     * IMPORTANT BEHAVIORS:
     *  - Creates message like "With pick #X in round Y of the 2026 IBL Draft, the **Team** select **Player!**"
     *  - Uses Markdown formatting (**bold** for team and player names)
     *  - Suitable for both Discord and HTML display
     *  - Does NOT include next team information or draft completion notices
     *  - All input parameters are used as-is (no sanitization – caller responsible)
     *  - NEVER throws exceptions
     *
     * Return Value:
     *  - String with announcement message in Markdown format
     *  - Does NOT include trailing newlines
     *  - Safe for passing to Discord::postToChannel()
     *
     * Examples:
     *  $msg = $processor->createDraftAnnouncement(5, 1, '2026', 'New York', 'John Smith');
     *  // Returns: "With pick #5 in round 1 of the 2026 IBL Draft, the **New York** select **John Smith!**"
     */
    public function createDraftAnnouncement(int $draftPick, int $draftRound, int $seasonYear, string $teamName, string $playerName): string;

    /**
     * Create the message for the next team on the clock
     *
     * Extends the base announcement message with information about whose turn is next.
     * If draft is complete, appends a completion message instead.
     *
     * @param string $baseMessage The base draft announcement (from createDraftAnnouncement)
     * @param string|null $discordID The Discord ID of the next team's owner, or null if draft is complete
     * @param string|null $seasonYear The year of the draft (used only for completion message)
     * @return string The complete message with next team info or draft completion notice
     *
     * IMPORTANT BEHAVIORS:
     *  - If discordID is not null: appends "**<@!DISCORD_ID>** is on the clock!" and module URL
     *  - If discordID is null: appends completion message "The 2026 IBL Draft has officially concluded!"
     *  - Adds Markdown formatting (@mention for next owner, bold/emoji for completion)
     *  - Suitable for posting to #draft-picks Discord channel
     *  - Does NOT sanitize inputs – caller responsible for validation
     *  - NEVER throws exceptions
     *
     * Return Value:
     *  - String with complete announcement and next team info (or completion message)
     *  - Multiple paragraphs separated by newlines
     *  - Contains Markdown @mention if discordID provided
     *  - Safe for Discord::postToChannel()
     *
     * Examples:
     *  $base = "With pick #5...New York...John Smith!";
     *  $msg = $processor->createNextTeamMessage($base, '123456789', '2026');
     *  // Returns: "With pick #5...John Smith!\n**<@!123456789>** is on the clock!\nhttps://www.iblhoops.net/..."
     *
     *  $msg = $processor->createNextTeamMessage($base, null, '2026');
     *  // Returns: "With pick #5...John Smith!\n**🏁 __The 2026 IBL Draft has officially concluded!__ 🏁**"
     */
    public function createNextTeamMessage(string $baseMessage, ?int $discordID, ?int $seasonYear): string;

    /**
     * Get the success message HTML for display
     *
     * Wraps the draft announcement in HTML with a link back to the draft module.
     * Used after a successful draft selection.
     *
     * @param string $message The draft announcement message
     * @return string HTML formatted success message
     *
     * IMPORTANT BEHAVIORS:
     *  - Wraps message in HTML paragraph tags
     *  - Appends a link to return to the Draft module
     *  - Converts newlines in message to HTML line breaks (<br> or preserved as-is depending on formatting)
     *  - Suitable for direct output to user
     *  - Input message is used as-is (no sanitization – caller responsible)
     *  - NEVER throws exceptions
     *
     * Return Value:
     *  - String with HTML formatted message and return link
     *  - Safe for direct echo to user
     *  - Contains working <a href> link to Draft module
     *
     * Examples:
     *  $html = $processor->getSuccessMessage('With pick #5...John Smith!');
     *  // Returns: "With pick #5...John Smith!<p>\n<a href=\"/ibl5/modules.php?name=Draft\">Go back...</a>"
     */
    public function getSuccessMessage(string $message): string;

    /**
     * Get the error message HTML for failed database updates
     *
     * Returns a generic error message when database operations fail during draft selection.
     * Instructs user to contact administrator.
     *
     * @return string HTML formatted error message
     *
     * IMPORTANT BEHAVIORS:
     *  - Returns pre-formatted HTML string (no parameters)
     *  - Generic message suitable for database errors (doesn't expose technical details)
     *  - Includes instruction to contact administrator
     *  - Includes link to return to Draft module
     *  - NEVER throws exceptions
     *
     * Return Value:
     *  - String with HTML formatted error message
     *  - Safe for direct echo to user
     *  - Contains working <a href> link to Draft module
     *
     * Examples:
     *  $html = $processor->getDatabaseErrorMessage();
     *  // Returns: "Oops, something went wrong, and at least one of the draft database tables wasn't updated..."
     */
    public function getDatabaseErrorMessage(): string;
}
