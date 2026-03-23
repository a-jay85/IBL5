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
];
