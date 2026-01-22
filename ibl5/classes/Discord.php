<?php

declare(strict_types=1);

class Discord
{
    /** @var \mysqli|MockDatabase Database connection (mysqli in production, MockDatabase in tests) */
    private $db;

    /** @var array<string, string> Discord webhook URLs loaded from config */
    private static array $webhooks = [];

    /** @var bool Whether config has been loaded */
    private static bool $configLoaded = false;

    /**
     * @param \mysqli|object $db Database connection (accepts MockDatabase for testing)
     */
    public function __construct($db)
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
            $config = require $configPath;
        } elseif (file_exists($examplePath)) {
            // Fallback to example config (e.g., in development without secrets set up)
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
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (string)($row['discordID'] ?? '');
    }

    public static function sendCurlPOST($url, $arrayContent)
    {
        // Defensive check: only send if Discord class exists (allows graceful degradation)
        if (!class_exists('Discord', false)) {
            return null;
        }

        // Skip actual HTTP calls during PHPUnit testing
        if (defined('PHPUNIT_RUNNING') || (defined('PHPUNIT_COMPOSER_INSTALL') && PHPUNIT_COMPOSER_INSTALL)) {
            return null;
        }

        $payload = json_encode(array("content" => $arrayContent));
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_RETURNTRANSFER => true,
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($error = curl_error($curl)) {
            throw new \Exception('cURL error: ' . $error);
        }
        
        // Discord webhook should return 204 No Content on success
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception('Discord webhook failed with HTTP ' . $httpCode . ': ' . $response);
        }
        
        // Note: curl_close() is deprecated in PHP 8.0+ - handle is automatically closed
        return $response;
    }

    public static function postToChannel($channelName, $messageContent)
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

        if ($url) {
            $messageContent = str_replace('<br>', "\n", $messageContent);
            Discord::sendCurlPOST($url, $messageContent);
        }
    }
}
