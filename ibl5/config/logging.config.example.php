<?php

/**
 * Logging Configuration Template
 *
 * Copy this file to logging.config.php and customise.
 *
 * IMPORTANT: Never commit logging.config.php to version control!
 * It is excluded via .gitignore.
 */

return [
    // Directory for log files. null = ibl5/logs/ (auto-resolved).
    'log_dir' => null,

    // Minimum log level: debug|info|notice|warning|error|critical|alert|emergency
    'level' => 'debug',

    // Number of daily log files to retain (0 = keep forever)
    'retention' => 30,

    // Log queries slower than this threshold (milliseconds). 0 = disabled.
    'slow_query_threshold_ms' => 200,

    // Per-channel retention overrides. Channels listed here get dedicated log files
    // (e.g. logs/ibl5-audit-YYYY-MM-DD.log) with their own retention period.
    'channel_retention' => [
        'audit' => 365,
        'admin' => 365,
    ],

    // Discord webhook URL for error alerting. null = disabled.
    'discord_webhook_url' => null,

    // Minimum level to trigger Discord alerts: error|critical|alert|emergency
    'discord_alert_level' => 'error',
];
