<?php

declare(strict_types=1);

class Discord
{
    private \mysqli $db;

    /** @var array<string, string> Discord webhook URLs loaded from config */
    private static array $webhooks = [];

    /** @var string IBLbot Express server URL for DM endpoint */
    private static string $iblbotUrl = '';

    /** @var bool Whether config has been loaded */
    private static bool $configLoaded = false;

    /**
     * @param \mysqli $db Database connection
     */
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        self::loadConfig();
    }

    /**
     * Load Discord webhook configuration from config file
     *
     * Loads webhooks from config/discord.config.php (gitignored, contains secrets)
     * Falls back to config/discord.config.example.php if config file doesn't exist
     *
     * @throws \RuntimeException if neither config file exists
     */
    private static function loadConfig(): void
    {
        if (self::$configLoaded) {
            return;
        }

        $configPath = __DIR__ . '/../config/discord.config.php';
        $examplePath = __DIR__ . '/../config/discord.config.example.php';

        if (file_exists($configPath)) {
            /** @var array{webhooks?: array<string, string>, iblbot_url?: string} $config */
            $config = require $configPath;
        } elseif (file_exists($examplePath)) {
            // Fallback to example config (e.g., in development without secrets set up)
            /** @var array{webhooks?: array<string, string>, iblbot_url?: string} $config */
            $config = require $examplePath;
        } else {
            throw new \RuntimeException(
                'Discord configuration file not found. ' .
                'Copy config/discord.config.example.php to config/discord.config.php and configure webhooks.'
            );
        }

        if (!isset($config['webhooks']) || !is_array($config['webhooks'])) {
            throw new \RuntimeException('Invalid Discord configuration: missing webhooks array');
        }

        self::$webhooks = $config['webhooks'];
        self::$iblbotUrl = $config['iblbot_url'] ?? '';
        self::$configLoaded = true;
    }

    public function getDiscordIDFromTeamname(string $teamname): string
    {
        $stmt = $this->db->prepare(
            "SELECT discordID FROM nuke_users WHERE user_ibl_team = ? LIMIT 1"
        );
        if ($stmt === false) {
            throw new \Exception('Prepare failed: ' . $this->db->error);
        }

        $stmt->bind_param('s', $teamname);
        if (!$stmt->execute()) {
            throw new \Exception('Execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            throw new \Exception('Failed to get result: ' . $stmt->error);
        }
        $row = $result->fetch_assoc();
        $stmt->close();

        return (string)($row['discordID'] ?? '');
    }

    /**
     * Send a POST request to a Discord webhook URL
     *
     * @param string $url Webhook URL
     * @param string $arrayContent Message content
     * @return string|null Response body or null if skipped
     */
    public static function sendCurlPOST(string $url, string $arrayContent): ?string
    {
        // Defensive check: only send if Discord class exists (allows graceful degradation)
        if (!class_exists('Discord', false)) {
            return null;
        }

        // Skip actual HTTP calls during PHPUnit testing
        if (defined('PHPUNIT_RUNNING') || (defined('PHPUNIT_COMPOSER_INSTALL') && PHPUNIT_COMPOSER_INSTALL)) {
            return null;
        }

        $payload = json_encode(["content" => $arrayContent]);
        if ($payload === false) {
            throw new \Exception('Failed to encode JSON payload');
        }

        if ($url === '') {
            throw new \Exception('Discord webhook URL cannot be empty');
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $error = curl_error($curl);
        if ($error !== '') {
            throw new \Exception('cURL error: ' . $error);
        }

        // Discord webhook should return 204 No Content on success
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception('Discord webhook failed with HTTP ' . $httpCode . ': ' . (is_string($response) ? $response : ''));
        }

        // Note: curl_close() is deprecated in PHP 8.0+ - handle is automatically closed
        return is_string($response) ? $response : null;
    }

    /**
     * Send a Discord DM via the IBLbot Express server
     *
     * Posts to the IBLbot /discordDM endpoint which sends a direct message
     * to the specified Discord user.
     *
     * @param string $recipientDiscordId Discord user ID of the recipient
     * @param string $message Message content to send
     * @return string|null Response body or null if skipped
     */
    public static function sendDM(string $recipientDiscordId, string $message): ?string
    {
        // Defensive check: only send if Discord class exists (allows graceful degradation)
        if (!class_exists('Discord', false)) {
            return null;
        }

        // Skip actual HTTP calls during PHPUnit testing
        if (defined('PHPUNIT_RUNNING') || (defined('PHPUNIT_COMPOSER_INSTALL') && PHPUNIT_COMPOSER_INSTALL)) {
            return null;
        }

        // Skip if recipient has no Discord ID
        if ($recipientDiscordId === '') {
            return null;
        }

        self::loadConfig();

        if (self::$iblbotUrl === '') {
            return null;
        }

        $payload = json_encode([
            'content' => [
                'receivingUserDiscordID' => $recipientDiscordId,
                'message' => $message,
            ],
        ]);
        if ($payload === false) {
            throw new \Exception('Failed to encode JSON payload for Discord DM');
        }

        $url = self::$iblbotUrl . '/discordDM';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $error = curl_error($curl);
        if ($error !== '') {
            throw new \Exception('cURL error sending Discord DM: ' . $error);
        }

        // IBLbot Express endpoint returns HTTP 200 on success
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception('Discord DM failed with HTTP ' . $httpCode . ': ' . (is_string($response) ? $response : ''));
        }

        return is_string($response) ? $response : null;
    }

    /**
     * Post a message to a Discord channel via webhook
     *
     * @param string $channelName Channel name (with or without # prefix)
     * @param string $messageContent Message content to post
     */
    public static function postToChannel(string $channelName, string $messageContent): void
    {
        // Defensive check: only send if Discord class exists (allows graceful degradation)
        if (!class_exists('Discord', false)) {
            return;
        }

        // Skip Discord posting during PHPUnit testing
        if (defined('PHPUNIT_RUNNING') || (defined('PHPUNIT_COMPOSER_INSTALL') && PHPUNIT_COMPOSER_INSTALL)) {
            return;
        }

        // Ensure config is loaded
        self::loadConfig();

        // Map channel names (with #) to config keys (without #)
        $channelKey = ltrim($channelName, '#');

        // Use testing webhook for localhost, otherwise use channel-specific webhook
        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        if ($serverName === "localhost" || $serverName === '127.0.0.1') {
            $url = self::$webhooks['testing'] ?? null;
        } else {
            $url = self::$webhooks[$channelKey] ?? null;
        }

        if ($url !== null) {
            $messageContent = str_replace('<br>', "\n", $messageContent);
            Discord::sendCurlPOST($url, $messageContent);
        }
    }
}
