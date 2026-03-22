<?php

declare(strict_types=1);

/**
 * Magic link endpoint for demo/hiring manager access.
 *
 * Validates a token and creates a read-only session as the Warriors GM.
 * POST requests are blocked globally in mainfile.php for demo sessions.
 *
 * Usage: /ibl5/demo-login.php?token=demo
 */

require_once __DIR__ . '/mainfile.php';

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';

// Validate token
if (!defined('DEMO_LOGIN_TOKEN') || !hash_equals(DEMO_LOGIN_TOKEN, $token)) {
    http_response_code(404);
    exit;
}

// Already logged in as demo user — just redirect
if (($authService->getUsername() ?? '') === 'ibl_demo') {
    header('Location: index.php');
    exit;
}

// Look up demo user
$demoUser = $mysqli_db->prepare("SELECT user_id FROM nuke_users WHERE username = 'ibl_demo' LIMIT 1");
$demoUser->execute();
$result = $demoUser->get_result();
$row = $result->fetch_assoc();
$demoUser->close();

if ($row === null) {
    http_response_code(404);
    exit;
}

// Create authenticated session (mirrors AuthService::startSession())
session_regenerate_id(true);
$_SESSION['auth_user_id'] = (int) $row['user_id'];
$_SESSION['auth_username'] = 'ibl_demo';
$_SESSION['demo_mode'] = true;

header('Location: index.php');
exit;
