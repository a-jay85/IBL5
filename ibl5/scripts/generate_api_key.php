<?php

declare(strict_types=1);

/**
 * CLI script to generate an API key for the IBL5 REST API.
 *
 * Usage: php scripts/generate_api_key.php "Owner Name" [permission_level] [rate_limit_tier]
 *
 * Examples:
 *   php scripts/generate_api_key.php "Discord Bot - MJ"
 *   php scripts/generate_api_key.php "Commissioner Tool" commissioner elevated
 */

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

if ($argc < 2) {
    echo "Usage: php scripts/generate_api_key.php \"Owner Name\" [permission_level] [rate_limit_tier]\n";
    echo "\n";
    echo "  permission_level: public (default), team_owner, commissioner\n";
    echo "  rate_limit_tier:  standard (default), elevated, unlimited\n";
    exit(1);
}

$ownerName = $argv[1];
$permissionLevel = $argv[2] ?? 'public';
$rateLimitTier = $argv[3] ?? 'standard';

$validPermissions = ['public', 'team_owner', 'commissioner'];
$validTiers = ['standard', 'elevated', 'unlimited'];

if (!in_array($permissionLevel, $validPermissions, true)) {
    echo "Error: Invalid permission_level. Must be one of: " . implode(', ', $validPermissions) . "\n";
    exit(1);
}

if (!in_array($rateLimitTier, $validTiers, true)) {
    echo "Error: Invalid rate_limit_tier. Must be one of: " . implode(', ', $validTiers) . "\n";
    exit(1);
}

// Bootstrap database connection
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

/** @var \mysqli $mysqli_db */

// Generate a random API key: ibl_ + 32 hex chars
$randomBytes = random_bytes(16);
$apiKey = 'ibl_' . bin2hex($randomBytes);
$keyHash = hash('sha256', $apiKey);
$keyPrefix = substr($apiKey, 0, 8);

$stmt = $mysqli_db->prepare(
    'INSERT INTO ibl_api_keys (key_hash, key_prefix, owner_name, permission_level, rate_limit_tier) VALUES (?, ?, ?, ?, ?)'
);

if ($stmt === false) {
    echo "Error: Failed to prepare statement: " . $mysqli_db->error . "\n";
    exit(1);
}

$stmt->bind_param('sssss', $keyHash, $keyPrefix, $ownerName, $permissionLevel, $rateLimitTier);

if ($stmt->execute() === false) {
    echo "Error: Failed to insert API key: " . $stmt->error . "\n";
    $stmt->close();
    exit(1);
}

$stmt->close();

echo "\n";
echo "API key generated successfully!\n";
echo "================================\n";
echo "Owner:       " . $ownerName . "\n";
echo "Permission:  " . $permissionLevel . "\n";
echo "Rate Limit:  " . $rateLimitTier . "\n";
echo "Key Prefix:  " . $keyPrefix . "\n";
echo "\n";
echo "API Key (save this - it cannot be retrieved later):\n";
echo $apiKey . "\n";
echo "\n";
