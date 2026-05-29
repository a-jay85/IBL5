<?php

declare(strict_types=1);

/**
 * Magic link endpoint for demo/hiring manager access.
 *
 * Validates a token and creates a read-only session as the Warriors GM.
 * POST requests are blocked globally in mainfile.php for demo sessions.
 *
 * Usage: /ibl5/demo-login.php?token=<configured DEMO_LOGIN_TOKEN>
 *
 * Demo login fails closed: it is disabled (HTTP 403) unless a non-weak token
 * is configured via the DEMO_LOGIN_TOKEN env var (or constant). See
 * Auth\DemoLoginGate and ADR-0034.
 */

require_once __DIR__ . '/mainfile.php';

use Auth\DemoLoginGate;

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$expectedToken = DemoLoginGate::resolveExpectedToken();

// Fail closed: if no token is configured, or it is the known-weak default
// ('demo'), demo login is disabled. Reject with 403 and establish no session,
// even if a stale config.php still defines the weak literal.
if (!DemoLoginGate::isEnabled($expectedToken)) {
    http_response_code(403);
    exit;
}

// Constant-time comparison of the supplied token against the configured one.
// A wrong-but-well-formed token keeps the endpoint's prior 404 obscurity.
if (!DemoLoginGate::isAuthorized($expectedToken, $token)) {
    http_response_code(404);
    exit;
}

// Already logged in as demo user — just redirect
if (($authService->getUsername() ?? '') === 'ibl_demo') {
    header('Location: index.php');
    exit;
}

// Look up demo user
$demoUser = $mysqli_db->prepare("SELECT id AS user_id FROM auth_users WHERE username = 'ibl_demo' LIMIT 1");
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
