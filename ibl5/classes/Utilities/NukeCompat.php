<?php

declare(strict_types=1);

namespace Utilities;

/**
 * Typed adapter for PHP-Nuke global functions.
 *
 * Wraps legacy PHP-Nuke functions (defined in mainfile.php) so that modern
 * classes can depend on an injectable, mockable object instead of bare globals.
 * PHPStan sees the types naturally through this class — no stub maintenance needed
 * for any function wrapped here.
 *
 * Usage:
 *   // In a controller constructor
 *   public function __construct(private readonly NukeCompat $nuke) {}
 *
 *   // Instead of: if (is_user($user)) { ... }
 *   if ($this->nuke->isUser($user)) { ... }
 */
class NukeCompat
{
    /**
     * Check if the current visitor is a logged-in user.
     *
     * @param mixed $cookie The $user cookie variable from PHP-Nuke
     */
    public function isUser(mixed $cookie): bool
    {
        return (bool) is_user($cookie);
    }

    /**
     * Decode the PHP-Nuke user cookie into its component parts.
     *
     * @param mixed $cookie The $user cookie variable
     * @return array<int, string>
     */
    public function cookieDecode(mixed $cookie): array
    {
        return cookiedecode($cookie) ?? [];
    }

    /**
     * Get user information array from the decoded cookie data.
     *
     * @param mixed $user The decoded user data
     * @return array<string, mixed>|null
     */
    public function getUserInfo(mixed $user): ?array
    {
        return getusrinfo($user);
    }

    /**
     * Check if the current visitor is an admin.
     *
     * @param mixed $admin The $admin variable from PHP-Nuke
     */
    public function isAdmin(mixed $admin = null): bool
    {
        return (bool) is_admin($admin);
    }

    /**
     * Format a Unix timestamp using the site's date format.
     */
    public function formatTimestamp(int|string $timestamp): string
    {
        return formatTimestamp($timestamp);
    }

    /**
     * Format a timestamp as a <time> element for client-side local timezone display.
     *
     * Returns an HTML <time> element with an ISO 8601 UTC datetime attribute.
     * JavaScript (local-time.js) converts the display to the user's local timezone.
     * If JS is disabled, the server-rendered UTC fallback remains visible.
     *
     * @return string Trusted HTML — do NOT pass through HtmlSanitizer
     */
    public function formatLocalTime(int|string $timestamp): string
    {
        $unixTime = $this->toUnixTimestamp($timestamp);
        $isoUtc = gmdate('c', $unixTime);
        $fallback = HtmlSanitizer::safeHtmlOutput(gmdate('l, F d @ H:i T', $unixTime));

        return '<time datetime="' . $isoUtc . '" class="local-time">' . $fallback . '</time>';
    }

    /**
     * Convert a timestamp (numeric or datetime string) to a Unix timestamp.
     */
    private function toUnixTimestamp(int|string $timestamp): int
    {
        if (is_numeric($timestamp)) {
            return (int) $timestamp;
        }

        $matches = [];
        preg_match(
            '/(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/',
            $timestamp,
            $matches
        );

        if (count($matches) < 7) {
            return 0;
        }

        $result = gmmktime(
            (int) $matches[4],
            (int) $matches[5],
            (int) $matches[6],
            (int) $matches[2],
            (int) $matches[3],
            (int) $matches[1]
        );

        return $result !== false ? $result : 0;
    }

    /**
     * Display the login box (outputs HTML directly).
     */
    public function loginBox(): void
    {
        loginbox();
    }

    /**
     * Build a redirect URL from the current request.
     */
    public function buildRedirectUrl(): ?string
    {
        return buildRedirectUrl();
    }

    /**
     * Get the current theme name.
     */
    public function getTheme(): string
    {
        return 'IBL';
    }

    /**
     * Load language file for a module.
     */
    public function getLang(string $module): void
    {
        get_lang($module);
    }

    /**
     * Display block content for a position (e.g., "Center", "Down").
     */
    public function blocks(string $position): void
    {
        blocks($position);
    }

    /**
     * Track online users.
     */
    public function online(): void
    {
        online();
    }

    /**
     * Output the theme header.
     */
    public function themeHeader(): void
    {
        themeheader();
    }

    /**
     * Output the theme footer.
     */
    public function themeFooter(): void
    {
        themefooter();
    }
}
