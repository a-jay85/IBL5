<?php

/**
 * Discord Integration Class
 * 
 * Handles Discord webhook integrations and user lookups for the IBL5 system.
 * This class provides methods to send messages to Discord channels and retrieve
 * Discord IDs for team owners.
 */
class Discord
{
    /**
     * Get Discord ID for a team owner
     * 
     * @param object $db Database connection object
     * @param string $teamname Team name to look up
     * @return string|null Discord ID or null if not found
     */
    public static function getDiscordIDFromTeamname($db, string $teamname): ?string
    {
        $escapedTeamname = $db->sql_escape_string($teamname);
        $query = "SELECT discordID FROM nuke_users WHERE user_ibl_team = '$escapedTeamname' LIMIT 1";
        $result = $db->sql_query($query);
        
        if ($result && $db->sql_numrows($result) > 0) {
            $row = $db->sql_fetchrow($result);
            return $row['discordID'] ?? $row[0] ?? null;
        }
        
        return null;
    }

    /**
     * Send a POST request to Discord webhook
     * 
     * @param string $url Webhook URL
     * @param array|string $arrayContent Content to send
     * @return array|null Response from webhook or null on error
     */
    public static function sendCurlPOST(string $url, $arrayContent): ?array
    {
        // Don't attempt to send if URL is not configured
        if (empty($url) || strpos($url, 'INSERTWEBHOOKURLHERE') !== false) {
            return null;
        }

        $payload = json_encode(['content' => $arrayContent]);
        
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($curl);

        if ($error = curl_error($curl)) {
            error_log("Discord webhook error: $error");
            curl_close($curl);
            return null;
        }

        curl_close($curl);
        return json_decode($response, true);
    }

    /**
     * Post a message to a Discord channel
     * 
     * @param string $channelName Channel name (e.g., '#trades')
     * @param string $messageContent Message to send
     * @return void
     */
    public static function postToChannel(string $channelName, string $messageContent): void
    {
        // Only attempt to post if not on localhost
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return;
        }

        $url = self::getWebhookUrlForChannel($channelName);
        
        // Don't attempt to send if webhook is not configured
        if (empty($url)) {
            return;
        }

        $messageContent = str_replace('<br>', "\n", $messageContent);

        self::sendCurlPOST($url, $messageContent);
    }

    /**
     * Get webhook URL for a specific channel
     * 
     * @param string $channelName Channel name
     * @return string|null Webhook URL or null if not configured
     */
    protected static function getWebhookUrlForChannel(string $channelName): ?string
    {
        // These should be configured in a config file or environment variables
        // For now, returning null will prevent errors when webhooks aren't configured
        $webhooks = [
            '#1v1-games' => getenv('DISCORD_WEBHOOK_1V1_GAMES') ?: null,
            '#draft-picks' => getenv('DISCORD_WEBHOOK_DRAFT_PICKS') ?: null,
            '#extensions' => getenv('DISCORD_WEBHOOK_EXTENSIONS') ?: null,
            '#free-agency' => getenv('DISCORD_WEBHOOK_FREE_AGENCY') ?: null,
            '#general-chat' => getenv('DISCORD_WEBHOOK_GENERAL_CHAT') ?: null,
            '#rookie-options' => getenv('DISCORD_WEBHOOK_ROOKIE_OPTIONS') ?: null,
            '#trades' => getenv('DISCORD_WEBHOOK_TRADES') ?: null,
            '#waiver-wire' => getenv('DISCORD_WEBHOOK_WAIVER_WIRE') ?: null,
        ];

        return $webhooks[$channelName] ?? null;
    }
}
