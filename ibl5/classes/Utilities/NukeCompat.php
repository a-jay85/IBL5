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
        return is_user($cookie);
    }

    /**
     * Decode the PHP-Nuke user cookie into its component parts.
     *
     * @param mixed $cookie The $user cookie variable
     * @return array<int, string>
     */
    public function cookieDecode(mixed $cookie): array
    {
        return cookiedecode($cookie);
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
        return is_admin($admin);
    }

    /**
     * Format a Unix timestamp using the site's date format.
     */
    public function formatTimestamp(int|string $timestamp): string
    {
        return formatTimestamp($timestamp);
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
        return get_theme();
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
     * Display the message box.
     */
    public function messageBox(): void
    {
        message_box();
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
