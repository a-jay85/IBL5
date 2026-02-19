<?php

declare(strict_types=1);

/**
 * Build a safe redirect URL from the session-stored query string.
 *
 * Called by login() after successful authentication. Reads
 * $_SESSION['redirect_after_login'], validates the module name,
 * rebuilds the URL with http_build_query(), and clears the session value.
 *
 * @return string|null The validated redirect URL, or null if no valid redirect exists
 */
function buildRedirectUrl(): ?string
{
    if (!isset($_SESSION['redirect_after_login']) || !is_string($_SESSION['redirect_after_login']) || $_SESSION['redirect_after_login'] === '') {
        return null;
    }

    $storedQuery = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);

    parse_str($storedQuery, $params);

    // Validate the module name
    $name = $params['name'] ?? null;
    if (!is_string($name) || $name === '' || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
        return null;
    }

    // Prevent redirect loop back to YourAccount
    if ($name === 'YourAccount') {
        return null;
    }

    // Filter out array params (only keep flat string key-value pairs)
    $safeParams = [];
    foreach ($params as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $safeParams[$key] = $value;
        }
    }

    if ($safeParams === []) {
        return null;
    }

    return 'modules.php?' . http_build_query($safeParams);
}
